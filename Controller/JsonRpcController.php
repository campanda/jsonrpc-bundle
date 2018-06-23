<?php
    
    namespace Wa72\JsonRpcBundle\Controller;

    use Stringy\StaticStringy;

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Serializer\SerializerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\Controller;
    
    use Campanda\SDK\Base\Delegate\BaseDelegateInterface;
    
    /**
     * Controller for executing JSON-RPC 2.0 requests
     * - SDK Delegates of the app-layer bricks of PandaEngine are added automatically as services
     *
     * @link http://www.jsonrpc.org/specification
     *
     * @license MIT
     * @author Christoph Singer
     * @author Helmut Hoffer von Ankershoffen
     *
     */
    class JsonRpcController extends Controller {

        const PARSE_ERROR = -32700;
        const INVALID_REQUEST = -32600;
        const METHOD_NOT_FOUND = -32601;
        const INVALID_PARAMS = -32602;
        const INTERNAL_ERROR = -32603;
        
        /**
         * Array of names of fully exposed services (all methods of this services are allowed to be called)
         *
         * @var array $services
         */
        private $services = [];
        
        /**
         * @param Request $httprequest
         * @param SerializerInterface $serializer
         * @return Response
         * @throws \ReflectionException
         */
        public function execute(Request $httprequest, SerializerInterface $serializer) {
            $json = $httprequest->getContent();
            $request = json_decode($json, true);
            $requestId = (isset($request['id']) ? $request['id'] : null);
            
            if ($request === null) {
                return self::getErrorResponse(self::PARSE_ERROR, null);
            } elseif (!(isset($request['jsonrpc']) && isset($request['method']) && $request['jsonrpc'] == '2.0')) {
                return self::getErrorResponse(self::INVALID_REQUEST, $requestId);
            }
            
            if (count($this->services) && strpos($request['method'], ':') > 0) {
                list($servicename, $method) = explode(':', $request['method']);
                if (!array_key_exists($servicename, $this->services)) {
                    return self::getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
                }
            } else {
                return self::getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
            }

            // lookup service in registry (no need for making services public ,-)
            $service = $this->services[$servicename];
            
            $params = (isset($request['params']) ? $request['params'] : []);
            
            if (is_callable([$service, $method])) {
                $r = new \ReflectionMethod($service, $method);
                $rps = $r->getParameters();
                
                if (is_array($params)) {
                    if (!(count($params) >= $r->getNumberOfRequiredParameters()
                        && count($params) <= $r->getNumberOfParameters())
                    ) {
                        return self::getErrorResponse(self::INVALID_PARAMS, $requestId,
                            sprintf('Number of given parameters (%d) does not match the number of expected parameters (%d required, %d total)',
                                count($params), $r->getNumberOfRequiredParameters(), $r->getNumberOfParameters()));
                    }
                    
                }
                if (self::isAssoc($params)) {
                    $newparams = [];
                    foreach ($rps as $i => $rp) {
                        /* @var \ReflectionParameter $rp */
                        $name = $rp->name;
                        if (!isset($params[$rp->name]) && !$rp->isOptional()) {
                            return self::getErrorResponse(self::INVALID_PARAMS, $requestId,
                                sprintf('Parameter %s is missing', $name));
                        }
                        if (isset($params[$rp->name])) {
                            $newparams[] = $params[$rp->name];
                        } else {
                            $newparams[] = null;
                        }
                    }
                    $params = $newparams;
                }
                
                // correctly deserialize object parameters
                foreach ($params as $index => $param) {
                    if (!$rps[$index]->isArray() && $rps[$index]->getClass() != null) {
                        $class = $rps[$index]->getClass()->getName();
                        $params[$index] = $serializer->deserialize($param, $class, 'json');
                    }
                }
    
                try {
                    $result = call_user_func_array([$service, $method], $params);
                } catch (\Exception $e) {
                    return self::getErrorResponse(self::INTERNAL_ERROR, $requestId, $e->getMessage());
                }
                
                $response = ['jsonrpc' => '2.0'];
                $response['result'] = $result;
                $response['id'] = $requestId;
                
                $response = $serializer->serialize($response, 'json');
                return new Response($response, 200, ['Content-Type' => 'application/json']);
            }
            
            return self::getErrorResponse(self::METHOD_NOT_FOUND, $requestId);
        }
        
        /**
         * Add a new service that is fully exposed by json-rpc
         * - Called from JsonRpcExposablePass
         *
         * @param string $serviceId The id of a service
         * @param BaseDelegateInterface $service The service
         */
        public function addService(string $serviceId, BaseDelegateInterface $service) {
            $this->services[$serviceId] = $service;
            
            // add reference to service given the delegate interface it implements as the
            // jsonrpc client (Campanda\SDK) cannot know the implementation on triggering
            // autoremoting
            $interfaces = class_implements(get_class($service));
            $delegateInterface = null;
            foreach ($interfaces as $interface) {
                if (StaticStringy::contains($interface, 'Campanda\SDK')
                    && StaticStringy::contains($interface, 'Delegate')) {
                    $delegateInterface = $interface;
                    break;
                }
            }
            if ($delegateInterface) {
                $this->services[$delegateInterface] = $service;
            }
        }
        
        // helpers
    
        /**
         * Get error data from code
         *
         * @param int $code
         * @return array
         */
        protected static function getError(int $code): array {
            $message = '';
            switch ($code) {
                case self::PARSE_ERROR:
                    $message = 'Parse error';
                    break;
                case self::INVALID_REQUEST:
                    $message = 'Invalid request';
                    break;
                case self::METHOD_NOT_FOUND:
                    $message = 'Method not found';
                    break;
                case self::INVALID_PARAMS:
                    $message = 'Invalid params';
                    break;
                case self::INTERNAL_ERROR:
                    $message = 'Internal error';
                    break;
            }
            
            return ['code' => $code, 'message' => $message];
        }
    
        /**
         * @param int $code
         * @param string $id
         * @param null $data
         * @return Response
         */
        protected static function getErrorResponse(int $code, string $id, $data = null): Response {
            $response = ['jsonrpc' => '2.0'];
            $response['error'] = self::getError($code);
            
            if ($data != null) {
                $response['error']['data'] = $data;
            }
            
            $response['id'] = $id;
            
            return new Response(json_encode($response), 200, ['Content-Type' => 'application/json']);
        }
        
        /**
         * Finds whether an array is associative
         *
         * @param array $var
         * @return bool
         */
        protected static function isAssoc(array $var): bool {
            return array_keys($var) !== range(0, count($var) - 1);
        }
        
    }
