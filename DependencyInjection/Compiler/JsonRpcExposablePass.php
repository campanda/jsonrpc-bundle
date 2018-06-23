<?php
    
    namespace Wa72\JsonRpcBundle\DependencyInjection\Compiler;
    
    use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Reference;

    /**
     *
     */
    class JsonRpcExposablePass implements CompilerPassInterface {
        
        const TAG = 'wa72_jsonrpc.exposable';
        
        /**
         * {@inheritDoc}
         */
        public function process(ContainerBuilder $container) {
            
            $definition = $container->getDefinition('wa72_jsonrpc.jsonrpccontroller');
            $services = $container->findTaggedServiceIds(self::TAG);
    
            foreach ($services as $id => $attr) {
                $definition->addMethodCall('addService', [$id, new Reference($id)]);
            }
        }
    }