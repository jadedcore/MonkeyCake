<?php
    /**
     * CakePHP 2.x Component to manage MailChimp API integration.
     * @see: http://book.cakephp.org/2.0/en/controllers/components.html
     */

    App::uses('Component', 'Controller');
    App::uses('Validation', 'Utility');
    App::uses('Security', 'Utility');
    class MailChimpComponent extends Component {

    /**
     * The base URL to use when connecting to your MailChimp instance.
     * @example: https://<dc>.api.mailchimp.com/3.0 (Replace <dc> with your data center)
     * @see: http://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/
     */
        public $baseURL = null;

    /**
     * Your mailchimp API key.
     * @see: http://kb.mailchimp.com/accounts/management/about-api-keys?utm_source=mc-api&utm_medium=docs&utm_campaign=apidocs&_ga=1.213049495.2001571705.1433334798
     */
        public $key = null;

    /**
     *
     */

        public $status = null;

    /**
     *
     */

        public $responseDetails = null;

    /**
     * ID of the list you would like to use for the specified list action.
     * @see: http://developer.mailchimp.com/documentation/mailchimp/reference/lists/
     */
        public $listID = null;

    /**
     * The full targetURL for the requested action. This will include any query parameters.
     */
        private $__targetURL = null;

    /**
     * The curl type necessary for the requested action.
     */
        private $__curlType = null;

    /**
     * The post data as a properly formatted JSON string to send MailChimp.
     * (NOT REQUIRED FOR EVERY ACTION)
     */
        private $__payload = null;

    /**
     * Called before the controller's beforeFilter method.
     *
     * If the baseURL or the API key were not already defined in the component array of your
     * controller, the initialize function will attempt to find them in the Configure array as
     * MailChimp.url or MailChimp.key. You can also configure them in the beforeFilter of your
     * controller.
     */
        public function initialize(Controller $controller){
            if(empty($this->baseURL)){
                if(Configure::check('MailChimp.url')){
                    $this->baseURL = Config::read('MailChimp.url');
                }
            }
            if(empty($this->key)){
                if(Configure::check('MailChimp.key')){
                    $this->key = Config::read('MailChimp.key');
                }
            }
        }

    /**
     * Startup is called after the controller's beforeFilter method but before the controller
     * executes the current action handler.
     *
     * The baseURL and API key must be defined by this point.
     */
        public function startup(Controller $controller){
            if(empty($this->baseURL)){
                $apiLink = 'http://developer.mailchimp.com/documentation/mailchimp/guides/get-started-with-mailchimp-api-3/';
                $message = 'You have not defined the base URL for your MailChimp server.';
                $message .= ' You can define this in the $components array as baseURL =>';
                $message .= ' https://*dc*.api.mailchimp.com/3.0';
                $message .= ' (Replace *dc* with your data center). You can also define this';
                $message .= " using Configure::write('MailChimp.url', 'Your URL'); See";
                $message .= ' <a href="'.$apiLink.'" target="_blank">MailChimp API</a>';
                $message .= ' for further assistance.';

                trigger_error(
                    $message,
                    E_USER_WARNING
                );
            }
            if(empty($this->key)){
                $apiLink = 'http://kb.mailchimp.com/accounts/management/about-api-keys';
                $apiLink .= '?utm_source=mc-api&utm_medium=docs&utm_campaign=apidocs';
                $apiLink .= '&_ga=1.213049495.2001571705.1433334798';
                $message = 'You have not defined your MailChimp API key. You can define this in';
                $message .= ' the $components array as key => *YourAPIKeyHERE*. You can also';
                $message .= " define this using Configure::write('MailChimp.key', 'YourAPIKey');";
                $message .= ' See <a href="'.$apiLink.'" target="_blank">MailChimp API</a> for';
                $message .= ' further assistance.';

                trigger_error(
                    $message,
                    E_USER_WARNING
                );
            }
        }

    /**
     * Add a new email to a list.
     * @param string $email - Properly formatted email address.
     * @param array $options - An array of request body parameters allowed by MailChimp
     * @return bool True if the request was successful otherwise false.
     * HTTP status code of the request is accessible at $this->status
     * Request details are accessible as a JSON string in $this->requestDetails
     * @see: http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/
     */
        public function newMember($email=null, $options=array()){
            if(empty($this->listID)){
                $this->__listError();
            }
            $this->__checkEmail($email);
            $defaultOptions = array(
                'email_type' => 'html',
                'status' => 'subscribed'
            );
            $options = array_merge($defaultOptions, $options);
            $this->__payload = $options;
            $this->__payload['email_address'] = $email;
            $this->__payload = json_encode($this->__payload);

            $this->__curlType = 'post';
            $this->__targetURL = $this->baseURL.'lists/'.$this->listID.'/members';
            $this->__executeRequest();
            if($this->status == '200'){
                return true;
            }
            return false;
        }

        public function removeMember($email=null){
            if(empty($this->listID)){
                $this->__listError();
            }
            $this->__checkEmail($email);
            $this->__curlType = 'delete';
            $this->__targetURL = $this->baseURL.'lists/'.$this->listID.'/'.'members/';
            $this->__targetURL .= $this->__getSubscriberHash($email);
            $this->__executeRequest();
            if($this->status == '204'){
                return true;
            }
            return false;
        }

        private function __executeRequest(){
            if(empty($this->__curlType)){
                throw new InvalidArgumentException('$__curlType must be defined.');
            }
            $curlHandle = curl_init($this->__targetURL);
            if(strtolower($this->__curlType) === 'post'){
                curl_setopt($curlHandle, CURLOPT_POST, TRUE);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->__payload);
            }
            else{
                curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, $this->__curlType);
            }
            curl_setopt($curlHandle, CURLOPT_USERPWD, 'username:'.$this->key);
    	 	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
    	 	$response = curl_exec($curlHandle);
            $httpStatus = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    	 	curl_close($curlHandle);
            $this->status = $httpStatus;
            $this->responseDetails = $response;
            CakeLog::write('debug', $this->status);
            CakeLog::write('debug', $this->responseDetails);
            return;
        }

        private function __getSubscriberHash($email = null){
            return Security::hash($email, 'md5', false);
        }

        private function __checkEmail($email=null){
            if(empty($email)){
                throw new InvalidArgumentException('No e-mail address was provided.');
            }
            if(!Validation::email($email)){
                throw new InvalidArgumentException($email.' is not a valid e-mail address.');
            }
            return;
        }

        private function __listError(){
            $apiLink = 'http://developer.mailchimp.com/documentation/mailchimp/reference/lists/';
            $message = 'This action requires a listID to be defined. Please define this in your';
            $message .= ' controller action prior to calling newMember() or define this in your';
            $message .= ' controller $component array. See';
            $message .= ' <a href="'.$apiLink.'" target="_blank">MailChimp API</a> for further';
            $message .= ' assistance.';
            trigger_error(
                $message,
                E_USER_ERROR
            );
        }
    }
?>
