<?php

namespace PayPal;



class HTTPException extends \RuntimeException
{

}



class PayPalException extends \RuntimeException
{
    /**
     * @var array Error info returned by PayPal
     */
    protected $errorInfo;



    /**
     * @param array $errorInfo Error info returned by PayPal
     * @param Exception $previous
     */
    public function __construct($errorInfo, Exception $previous = NULL)
    {
        parent::__construct($errorInfo['L_LONGMESSAGE0'], $errorInfo['L_ERRORCODE0'], $previous);
        $this->errorInfo = $errorInfo;
    }



    /**
     * @return array Error info returned by PayPal
     */
    public function getErrorInfo()
    {
        return $this->errorInfo;
    }
}
