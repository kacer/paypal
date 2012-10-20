<?php

use Nette\Application\Responses\TextResponse;
use Nette\Diagnostics\Debugger;
use PayPal\PayPal;
use PayPal\PayPalException;

/**
 * PayPal example presenter.
 */
class PayPalPresenter extends BasePresenter
{
    protected function beforeRender()
    {
        parent::beforeRender();
        $this->getHttpResponse()->setContentType('text/html');
    }



	public function renderDefault()
	{
        $link = htmlspecialchars($this->link('pay!'));
        $this->sendResponse(new TextResponse('<a href="' . $link . '">Pay $1 with PayPal!</a>'));
	}



    public function handlePay()
    {
        try {
            $this->getPaypal()->doExpressCheckout(1, 'ExamplePresenter',
                    $this->link('//return'), $this->link('//cancel'), 'aUSD');

        } catch (PayPalException $ex) {
            $this->sendResponse(new TextResponse('<h1>Oops!</h1>' . Debugger::dump($ex->getErrorInfo(), TRUE)));
        }
    }



    public function renderReturn($token, $PayerID)
    {
        try {
            dump($this->getPaypal()->doPayment($token, $PayerID));
            $this->sendResponse(new TextResponse('<h1>Thank you!</h1>' . Debugger::dump($error, TRUE)));

        } catch (PayPalException $ex) {
            $this->sendResponse(new TextResponse('<h1>Oops!</h1>' . Debugger::dump($ex->getErrorInfo(), TRUE)));
        }
    }



    public function renderCancel()
    {

    }



    private function getPaypal()
    {
        $params = $this->getContext()->getParameters();
        return new PayPal(
            $params['paypal_username'],
            $params['paypal_password'],
            $params['paypal_signature']
        );
    }

}
