<?php
    
namespace Wa72\JsonRpcBundle\Tests\Fixtures;

class Testparameter {
    
    /**
     * @var string
     */
    private $a;

    /**
     * @var string
     */
    protected $b;

    /**
     * @var string
     */
    public $c;

    /**
     * @param string $a
     */
    public function __construct($a)
    {
        $this->a = $a;
    }

    /**
     * @return string
     */
    public function getA()
    {
        return $this->a;
    }

    /**
     * @return string
     */
    public function getB()
    {
        return $this->b;
    }

    /**
     * @param string $b
     */
    public function setB($b)
    {
        $this->b = $b;
    }

    /**
     * @return string
     */
    public function getC()
    {
        return $this->c;
    }

    /**
     * @param string $c
     */
    public function setC($c)
    {
        $this->c = $c;
    }

}