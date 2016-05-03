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
     * The HTTP status code for any curl request that was executed.
     */

        public $status = null;

    /**
     * The detailed response from the MailChimp API regarding any curl request that was performed.
     * This will be a JSON string.
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
     * @param Controller $controller
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
     * @param Controller $controller
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
            $this->__curlType = 'post';
            $this->__targetURL = $this->baseURL.'lists/'.$this->listID.'/members';
            $defaultOptions = array(
                'email_type' => 'html',
                'status' => 'subscribed'
            );
            $options = array_merge($defaultOptions, $options);
            $this->__payload = $options;
            $this->__payload['email_address'] = $email;
            $this->__payload = json_encode($this->__payload);
            $this->__executeRequest();
            if($this->status == '200'){
                return true;
            }
            return false;
        }

    /**
     * Get information about list members or about a specific list member.
     * @param string $email - (Optional) A properly formatted email address.  If this is not
     * provided, all members of a list will be retrieved.
     * @param array $options - (Optional) An array of request body parameters allowed by MailChimp
     * @return bool - True if the request was successful, otherwise false.
     * HTTP status code of the request is accessible at $this->status
     * Request details are accessible as a JSON string in $this->requestDetails
     * @see: http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/
     */
        public function getMemberInfo($email=null, $options=array()){
            if(empty($this->listID)){
                $this->__listError();
            }
            if(!empty($email)){
                $this->__checkEmail($email);
            }
            $this->__curlType = 'get';
            $this->__targetURL = $this->baseURL.'lists/'.$this->listID.'/members';
            if(!empty($email)){
                $this->__targetURL .= '/'.$this->__getSubscriberHash($email);
            }
            if(!empty($options)){
                $this->__formQueryParams($options);
            }
            $this->__executeRequest();
            if($this->status == '200'){
                return true;
            }
            return false;
        }

    /**
     * Update information about a list member.
     * @param string $email - Properly formatted email address.
     * @param array $options - An array of request body parameters allowed by MailChimp
     * @return bool True if successful, otherwise false.
     * HTTP status code of the request is accessible at $this->status
     * Request details are accessible as a JSON string in $this->requestDetails
     * @see: http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/
     */
        public function updateMember($email=null, $options=array()){
            if(empty($this->listID)){
                $this->__listError();
            }
            $this->__checkEmail($email);
            $this->__curlType = 'patch';
            $this->__targetURL = $this->baseURL.'lists/'.$this->listID.'/members';
            $this->__targetURL .= '/'.$this->__getSubscriberHash($email);
            $this->__payload = $options;
            $this->__payload = json_encode($this->__payload);
            CakeLog::write('debug', print_r($this->__payload, true));
            CakeLog::write('debug', print_r($this->__targetURL, true));
            $this->__executeRequest();
            if($this->status == '200'){
                return true;
            }
            return false;
        }

    /**
     * Remove an existing email from a list.
     * @param string $email - Properly formatted email address.
     * @return bool True if the request was successful otherwise false.
     * HTTP status code of the request is accessible at $this->status
     * Request details are accessible as a JSON string in $this->requestDetails
     * @see: http://developer.mailchimp.com/documentation/mailchimp/reference/lists/members/
     */
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

    /**
     * Execute the actual curl request and communicate with the MailChimp API
     * This will store the HTTP Status Code and the the JSON string results in
     * the Class properties $status and $responseDetails respectively.
     * @return void
     */
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
                if(!empty($this->__payload)){
                    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->__payload);
                }
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

    /**
     * Turns an email address into the MD5 hash that MailChimp expects to receive in order
     * to identify a member in a list.
     * @param string $email - Properly formatted email address.
     * @return string MD5 hash of provided email address
     */
        private function __getSubscriberHash($email = null){
            return Security::hash($email, 'md5', false);
        }

    /**
     * Checks to ensure an email was provided and that it is a properly formatted
     * email address. If the email address is missing, or not formatted correctly,
     * an error will be thrown.
     * @param string $email - email address to check for format.
     * @return void
     */
        private function __checkEmail($email=null){
            if(empty($email)){
                throw new InvalidArgumentException('No e-mail address was provided.');
            }
            if(!Validation::email($email)){
                throw new InvalidArgumentException($email.' is not a valid e-mail address.');
            }
            return;
        }

    /**
     * Format a query string based on the given options array. This requires that any
     * property level value that is not itself an array of subObjects that you specify
     * true as the value.
     * @example:
     *   $options = array(
     *      'fields' => array(
     *          'merge_fields' => array(
     *              'FNAME' => 'Commander',
     *              'LNAME' => 'Shepard')
     *          ),
     *          'status' => true
     *      ),
     *      'count' => 10
     *  );
     * @param array $options - Array of options to turn into query string parameters
     * @return void
     */
        private function __formQueryParams($options=array()){
            if(isset($options['fields']) && isset($options['exclude_fields'])){
                $apiLink = 'http://developer.mailchimp.com/documentation/mailchimp/guides/';
                $apiLink = 'get-started-with-mailchimp-api-3/#partial-responses';
                $message = 'You can not include fields and exclude_fields in the same request';
                $message .= ' as they are mutually exclusive. See';
                $message .= ' <a href="'.$apiLink.'" target="_blank">MailChimp API</a> for';
                $message .= ' further assistance.';
                trigger_error(
                    $message,
                    E_USER_ERROR
                );
            }
            $query = '';
            $firstParam = true;
            foreach($options as $param => $option){
                if($firstParam){
                    $query .= '?';
                    $firstParam = false;
                }
                else{
                    $query .= '&';
                }
                $query .= $param.'=';
                if(!is_array($option)){
                    $query .= $option;
                }
                else{
                    $firstProperty = true;
                    foreach($option as $property => $subObjects){
                        if(!is_array($subObjects)){
                            if(!$firstProperty){
                                $query .= ',';
                            }
                            else{
                                $firstProperty = false;
                            }
                            $query .= $property;
                        }
                        else{
                            foreach($subObjects as $object => $value){
                                if(!$firstProperty){
                                    $query .= ',';
                                }
                                else{
                                    $firstProperty = false;
                                }
                                $query .= $property.'.'.$value;
                            }
                        }
                    }
                }
            }
            $this->__targetURL .= $query;
            CakeLog::write('debug', print_r($options, true));
            CakeLog::write('debug', $this->__targetURL);
            return;
        }

    /**
     * The error to use when a ListID has not been properly defined for a method that
     * requires a ListID.
     */
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
