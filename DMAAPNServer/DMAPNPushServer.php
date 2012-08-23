<?php
    
    require_once 'DMAPNMessage.php';
    require_once 'DMAPNFeedbackService.php';
    
    #################################################################################
    ## Developed by Daniele Margutti                                               ##
    ## http://www.danielemargutti.com                                              ##
    ## mail: daniele.margutti@gmail.com                                            ## 
    ## ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ ##
    ##                                                                             ##
    ## THIS SOFTWARE IS PROVIDED BY MANIFEST INTERACTIVE 'AS IS' AND ANY           ##
    ## EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE         ##
    ## IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR          ##
    ## PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL MANIFEST INTERACTIVE BE          ##
    ## LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR         ##
    ## CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF        ##
    ## SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR             ##
    ## BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,       ##
    ## WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE        ##
    ## OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,           ##
    ## EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.                          ##
    ##                                                                             ##
    ## ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ ##
    #################################################################################

    class DMAPNException extends Exception {
        
    }
    
    /**
    * @category Apple Push Notification Service using PHP
    * @package  DMAPNPushServer
    * @author   Daniele Margutti <daniele.margutti@gmail.com>
    * @license  http://www.apache.org/licenses/LICENSE-2.0
    * @link     https://github.com/malcommac/DMAPNServer
    */

    /**
    * Begin Document
    */
    
    class DMAPNServer {
        
        // You need to convert your certificates to .pem format using ssl commands from terminal:
        //
        // or from here: https://www.sslshopper.com/ssl-converter.html
        
        const   kAPPLEAPN_HOSTNAME_DEVELOPMENT      =   "gateway.sandbox.push.apple.com";   // Address for Development Provisioning profile cert
        const   kAPPLEAPN_HOSTNAME_PRODUCTION       =   "gateway.push.apple.com";           // Address for Production Provisioning profile cert
        const   kAPPLEAPN_PORT                      =   2195;                               // Default port (it's the same for dev/prod)
        const   kREPORT_UNRECOVERABLE_ERROR         =   -2;
        const   kREPORT_LOGMESSAGE                  =   0;
        const   kREPORT_ERROR                       =   -1;
        
        // List of available error's code we can receive from APN server
        private $kPUSH_ERRORS                        = array(
                                                            0 => 'No errors encountered',
                                                            1 => 'Processing error',
                                                            2 => 'Missing device token',
                                                            3 => 'Missing topic',
                                                            4 => 'Missing payload',
                                                            5 => 'Invalid token size',
                                                            6 => 'Invalid topic size',
                                                            7 => 'Invalid payload size',
                                                            8 => 'Invalid token',
                                                            255 => 'None (unknown)',
                                                    );
        
        private $apn_host                           =   null;         // Apples Sandbox/Production APNS Gateway
        private $apn_port                           =   2195;         // APN port (generally 2195)
        private $apn_certificate                    =   null;         // Absolute path to your Production Certificate (PEM)
        private $apn_certificatePswd                =   "";           // Certificate password (if set)
        private $apn_queuedMessages                 =   array();      // List of queued messages
     
        private $apn_socket                         =   null;         // Stream connected to APNS server
        private $apn_logfilename                    =   null;         // Log path for APNS errors
        private $apn_errorsOccurred                 =   array();      // Errors occurred
        private $apn_messageRetryAttempts           =   3;            // Number of max retry attempts to made before give up message sending
        
        /**
	 * Constructor.
	 *
	 * Initializes a new server connection.
         * The example below initialize a new server using a production certificate.
         * 
	 * <code>
	 * <?php
	 * $APN = new DMAPNPushServer(false);
	 * $APN->setCertificate("production_cert.pem","");
	 * ?>
 	 * </code>
	 *
	 * @param       boolean $isDevelopmentCert    true to initialize a development environment (you must use a development certificate), false to use a production certificate
	 * @access 	public
	 */
        function __construct($isDevelopmentCert=false) {
            $this->setConnection(($isDevelopmentCert == true ? self::kAPPLEAPN_HOSTNAME_DEVELOPMENT : self::kAPPLEAPN_HOSTNAME_PRODUCTION),self::kAPPLEAPN_PORT);
	}
        
        /**
	 * Set a certificate for this connection.
         * You must set it before connecting to the server
	 *
         * You can have two different kinds of certificate:
         *  - PRODUCTION CERTIFICATE:   generated by an adhoc/appstore provisioning profile.
         *  - DEVELOPMENT CERTIFICATE:  generated by a development provisioning profile (it uses Apple's APN Sandbox address)
	 *
	 * @param   string  $cert_path         certificate path in PEM format. If you have a P12 certificate you can use https://www.sslshopper.com/ssl-converter.html
         * @param   string  $cert_password     (optional) your certificate password if set. leave and empty string if you have not set any password.
	 * @access  public
	 */
        
        function setCertificate($cert_path = null,$cert_password = "",$enable_debug=false) {
            if (file_exists($cert_path) == false) {
                throw new DMAPNException("Given certificate does not exist at '$cert_path'");
            } else {
                $this->apn_certificate = $cert_path;
                $this->apn_certificatePswd = $cert_password;
                
                if ($enable_debug == true)
                    $this->apn_logfilename = "DMAPNPushServer_".date("m.d.y.H.M.s",  time());
            }
        }
        
         /**
	 * Initialize connection hostname and port. You should not use it directly, it's set by server constructor function
          * 
	 * @param   string  $host_name         server address
         * @param   string  $host_port         server port
	 * @access  private
	 */
        
        protected function setConnection($host_name="gateway.push.apple.com",$host_port=2195) {
            if ($host_name == null || $host_port == 0) {
                throw new DMAPNException("Connection parameters are not valid {host=$host_name,port=$host_port}");
            } else {
                $this->apn_host = $host_name;
                $this->apn_port = $host_port;
            }
        }
        
        /**
	 * Add a new message to server queue. Use connect(), then sendMessages() to start sending process
         * 
         * <code>
	 * <?php
	 * $APN = new DMAPNPushServer(false);
	 * $APN->setCertificate("production_cert.pem","");
         * 
         * $message = new DMAPNMessage("device_uuid_string","hello, this is a message!");
         * $APN->addMessage($message);
         * $APN->connect();
         * $APN->sendMessages();
	 * ?>
 	 * </code>
         * 
	 * @param   DMAPNMessage  $message     a message class
	 * @access  public
	 */
        
        function addMessage(DMAPNMessage $message) {
            if (is_a($message, "DMAPNMessage") == false)
                throw new DMAPNException("Given message object to queue must be DMAPNMessage class istance.");
            else {
                $this->apn_queuedMessages[] = $message;
            }
        }
        
        /**
	 * Add an array of messages inside server queue. Each message must be a DMAPNMessage object
         * 
	 * @param   array  $messages     array of DMAPNMessage objects to add
         * @return  bool                 true if added, false otherwise
	 * @access  public
	 */
        
        function addMessages($messagesArray) {
            if (is_array($messagesArray) && count($messagesArray) > 0) {
                $this->apn_queuedMessages = array_merge ($this->apn_queuedMessages,$messagesArray);
                return true;
            } else return false;
        }
        
         /**
	 * Remove a message from queue's list by specific it's unique identifier.
         * 
         * <code>
	 * <?php
         * $message = new DMAPNMessage("device_uuid_string","hello, this is a message!");
         * $APN->addMessage($message);
         * 
         * $APN->removeMessageWithIdentifier($message->messageIdentifier());
	 * ?>
 	 * </code>
         * 
	 * @param   string  $identifier     unique message identifier, generated automaticall at runtime
         * @return  bool                    true if message was removed successfully, false otherwise
	 * @access  public
	 */
        function removeMessageWithIdentifier($identifier) {
            if (!key_exists($identifier, $this->apn_queuedMessages)) {
                throw new DMAPNException("Invalid message ID '{$identifier}'. There is not any message with this identifier.");
                return false;
            } else {
                unset($this->apn_queuedMessages[$identifier]);
                return true;
            }
        }
        
        /**
	 * Return a message from queue's list with specified identifier
         * 
	 * @param   string  $identifier     unique message identifier, generated automaticall at runtime
         * @return  DMAPNMessage            requested message
	 * @access  public
	 */
        function messageWithIdentifier($identifier) {
            if (!key_exists($identifier,$this->apn_queuedMessages[$identifier])) {
                throw new DMAPNException("Invalid message ID '{$identifier}'. There is not any message with this identifier.");
                return null;
            } else
                return $this->apn_queuedMessages[$identifier];
        }
        
        /**
	 * Return a list of queued messages
         * 
	 * @access  public
         * @return  array|DMAPNMessage   a list of queued messages
	 */
        function queuedMessages() {
            return array_values($this->apn_queuedMessages);
        }

        /**
	 * Used to generate a new message log record
         * 
	 * @param   string  $message     a message record to store
	 * @access  private
	 */
        function logMessage($message) {
            if ($this->apn_logfilename == null) // log it's not enabled
                return false;
            
            $message = "[".date("m.d.y.H.M.s",  time())."] ".$message;
            if (file_exists("logs") == false)
                mkdir("logs");
            
            $now_date = date('Y-m-d H:i:s',time());
            $myFile = "logs/".$this->apn_logfilename.".log";
            if (file_exists($myFile) == true) {
                $fh = fopen($myFile, 'a') or die("can't open file");

                if (strlen($message) > 0 && $message[0] == "\n")
                    fwrite($fh, $message);
                else
                    fwrite($fh, $now_date." : ".$message."\n");
                fclose($fh);
            } else {
                file_put_contents($myFile, $now_date." : ".$message."\n");
            }
            return true;
        }
     
        /**
	 * Establish a new stream connection to the server
         * 
         * @return  bool            yes if connection was activated, false otherwise
	 * @access  public
	 */
        function connect() {
            $this->logMessage("Using certificate at: ".$this->apn_certificate.":".$this->apn_certificatePswd);
            
            $streamContext = stream_context_create();
            stream_context_set_option($streamContext, 'ssl', 'local_cert', $this->apn_certificate);
            stream_context_set_option($streamContext, 'ssl', 'passphrase', $this->apn_certificatePswd);
	
            $error = null; $errorString=null;
            $connection_string = 'ssl://'.$this->apn_host.':'.$this->apn_port;
            $this->logMessage("Connecting at APN: $connection_string");
            $this->apn_socket = stream_socket_client($connection_string, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $streamContext);
            
            if(!$this->apn_socket) {
		$this->disconnect();
                $this->logMessage("Failed to connect at ".$this->apn_host.":".$this->apn_port.": ($error=$errorString)");
                throw new DMAPNException("Failed to connect at ".$this->apn_host.":".$this->apn_port.": ($error=$errorString)");
                return false;
            } else {
                return true;
            }
        }
        
        /**
	 * Close server connection
         * 
	 * @access  public
	 */
        function disconnect() {
            fclose($this->apn_socket); 
            $this->logMessage("Connection closed");
        }
        
        
        /**
	 * Iterate Messages and send them to the stream connection.
         * Use it when you have completed your messages queue and want to send them.
         * You can use $APN->errorsOccurred() to get a list of errors occurred during the process.
         * 
         * <code>
	 * <?php
         * ..
         * $APN->addMessage($message0);
         * $APN->addMessage($message1);
         * ..
         * $APN->addMessage($messageN);
         * 
         * $APN->connect();
         * $result_stats = $APN->sendMessages();
         * $APN->disconnect();
	 * ?>
 	 * </code>
         * 
         * @return  array            a dictionary with several {key,value} records: "queued_messages" (count initial queued messages),"total_notifications" (count total notifications to send), "sent_notifications" (notifications sent successfully, "failed_notifications" (total # of failed notifications)
	 * @access  public
	 */
        
        function sendMessages() {
            
            $this->logMessage(count($this->apn_queuedMessages)." messages to send");
            
            $this->apn_errorsOccurred = array();
            
            $current_msg_idx = 0;
            $remaining_msgs_tosend = count($this->apn_queuedMessages);
            $total_notifications_sent = 0;
            $total_notifications = 0;
            
            while ($remaining_msgs_tosend > 0) {
                $messageObj = $this->apn_queuedMessages[$current_msg_idx];
                $msgID = $messageObj->messageIdentifier(); // get message UUID
                
                // for each message return an array of message's representation for each listed recipient
                $notificationsData = $messageObj->payloadBinaryRepresentations();
                // so we have n notification for each message package (where n >= 1)
                $total_notifications+= count($notificationsData);
                
                foreach ($notificationsData as $destUUID => $payloadBinaryData) {
                    // for each destination retry until done or max retry attempts reached
                    
                    while ($this->_retryAttemptsForMessage($msgID, $destUUID) < $this->apn_messageRetryAttempts) {
                        $error_result = $this->sendNotificationPacket($payloadBinaryData);
                        $message_sent = ($error_result == null);
                        if (!$message_sent) { // an error has occurred
                            // store this attempt
                            $this->_reportSendingErrorForMessage($msgID, $destUUID, $error_result);
                            
                            if ($error_result["error"] == self::kREPORT_UNRECOVERABLE_ERROR) { // an unrecoverable error has occurred
                                $this->disconnect();
                                return false;
                            }
                        } else {
                            // notification sent successfully
                            $total_notifications_sent++;
                            break;
                        }
                    } 
                }
                $current_msg_idx++;
                $remaining_msgs_tosend--;
            }
            
            $not_sent_messages = ($total_notifications-$total_notifications_sent);
            $this->logMessage("$total_notifications_sent notifications sent in ".count($this->apn_queuedMessages)." messages packets");
            if ($not_sent_messages > 0)
                $this->logMessage("$not_sent_messages messages were not sent due to some errors");
            else $this->logMessage ("All messages were sent successfully");
            
            return array("queued_messages"      => count($this->apn_queuedMessages),
                         "total_notifications"  => $total_notifications,
                         "sent_notifications"   => $total_notifications_sent,
                         "failed_notifications" => $not_sent_messages);
        }
        
         /**
	 * Return a list of errors occurred during sendMessages() process.
         * It's an array of dictionaries with several keys. Each record is related to a failed send of a message and it's identified by "message_id" key.
         * Inside each record you will find a list of failed recipients with these keys:
         *      - "message_id"  unique message identifier
         *      - "attempts"    number of sending attempts made before give up (max is specified by $APN->setRetryAttempts())
         *      - "errors"      a list of errors occurred during each attempt (contains reply from APN server as dictionary with these keys: "status_code","message","info")       
         *     
         * 
         * @return  array       a list of failed messages. Inside a dictionary of error for each receipient of the message.
	 * @access  public
	 */
        function errorsOccurred() {
            return $this->apn_errorsOccurred;
        }
        
        /**
	 * Set the number of max attempts to send a single before give up
         * 
	 * @param   int         $maxAttempts     # of attempts (>=1)
	 * @access  public
	*/
        function setRetryAttempts($maxAttempts =3) {
            if ($maxAttempts >= 1) $this->apn_messageRetryAttempts = $maxAttempts;
        }
        
        /**
	 * Return the number of max attempts made to send a single before give up
         * 
         * @return  int         # of attempts
	 * @access  public
	*/
        function maxRetryAttempts() {
            return $this->apn_messageRetryAttempts;
        }
      
        
        /**
	 * Return the number of attempts made to send a single pair {message,recipient}
         * 
	 * @return  int         attempts made
	 * @access  private
	*/
        protected function _retryAttemptsForMessage($msgID,$destUUID) {
            return $this->apn_errorsOccurred[$msgID][$destUUID]["attempts"];
        }
        
        /**
	 * Report an error occurred while sending a pair {message,recipient}
         * 
	 * @param   string  $msgID          unique message identifier
         * @param   string  $destUUID       message recipient (a message can have multiple recipient)
         * @param   array   $error_info     description of the error as dictionary with status_code,error,info keys
	 * @access  private
	*/
        protected function _reportSendingErrorForMessage($msgID,$destUUID,$error_info) {
            $this->apn_errorsOccurred[$msgID][$destUUID]["message_id"] = $msgID;
            $this->apn_errorsOccurred[$msgID][$destUUID]["attempts"] = $this->_retryAttemptsForMessage($msgID, $destUUID)+1;
            $this->apn_errorsOccurred[$msgID][$destUUID]["errors"][] = $error_info;
        }
        
        /**
	 * Send a single notification packet
         * 
	 * @param   string  $binaryRepresentation       payload binary representation to send
         * @return  array                               null if message was sent, a dictionary with status_code,error,info keys if a message has occurred.
	 * @access  private
	*/
        protected function sendNotificationPacket($binaryRepresentation) {
            if (!$this->apn_socket) {
                $this->_reportMessage(  self::kREPORT_LOGMESSAGE, 
                                        "APN Connection dropped/closed.Now resuming...");
                if (!$this->connect())
                    return $this->_reportMessage (  self::kREPORT_UNRECOVERABLE_ERROR, 
                                                    "APN Connection was closed and cannot be resumed.");
                else
                    $this->_reportMessage ( self::kREPORT_LOGMESSAGE,
                                            "Connection resumed. Now continuing...");
            }
            
            $fwrite = fwrite($this->apn_socket, $binaryRepresentation);
            if (!$fwrite) {
                $this->disconnect();
                return $this->_reportMessage(   self::kREPORT_UNRECOVERABLE_ERROR,
                                                "Cannot write to stream");
            } else {
                // "Provider Communication with Apple Push Notification Service"
		// http://developer.apple.com/library/ios/#documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/CommunicatingWIthAPS/CommunicatingWIthAPS.html#//apple_ref/doc/uid/TP40008194-CH101-SW1
                // "If you send a notification and APNs finds the notification malformed or otherwise unintelligible, it
		// returns an error-response packet prior to disconnecting. (If there is no error, APNs doesn't return
		// anything.)"
		// 
		// This complicates the read if it blocks.
		// The timeout (if using a stream_select) is dependent on network latency.
		// default socket timeout is 60 seconds
		// Without a read, we leave a false positive on this push's success.
		// The next write attempt will fail correctly since the socket will be closed.
		//
		// This can be done if we start batching the write

		// Read response from server if any. Or if the socket was closed.
		// [Byte: data.] 1: 8. 1: status. 4: Identifier.
                $tv_sec = 1;
                $tv_usec = null; // Timeout. 1 million micro seconds = 1 second
		$r = array($this->apn_socket);
                $we = null; // Temporaries. "Only variables can be passed as reference."
		$numChanged = stream_select($r, $we, $we, $tv_sec, $tv_usec);
		if(false===$numChanged) {
                    $this->_reportMessage(  self::kREPORT_ERROR,
                                            "Failed selecting stream to read.");
		}
                else if($numChanged>0) {
                    $command = ord(fread($this->apn_socket, 1));
                    $status = ord(fread($this->apn_socket, 1));
                    $identifier = implode('', unpack("N", fread($this->apn_socket, 4)));

                    if ($status > 0) {
                        $error_reason = isset($kPUSH_ERRORS[$status]) ? $kPUSH_ERRORS[$status] : 'Unknown error';
                        $info_dict = array("description"    => $error_reason,
                                           "identifier"     => $identifier);
                        // The socket has also been closed. Cause reopening in the loop outside.
                        $this->disconnect();
                        return $this->_reportMessage(self::kREPORT_ERROR,
                                                     "APNs responded with {command=[$command],status=[$status],pid=[$identifier]}",
                                                     $info_dict);
                    } else {
                        // Apple docs state that it doesn't return anything on success though
                        $this->_reportMessage(  self::kREPORT_LOGMESSAGE,
                                                "Message sent successfully to identifier [$identifier]");
			return null; // push sent
                    }
		} else {
                    $this->_reportMessage(  self::kREPORT_LOGMESSAGE,"Message sent successfully");
                    return null; // push sent
		}
            }
        }
        
        /**
	 * Create an error package for a sending process and log it.
         * 
	 * @param   string  $code               APN result error code
         * @param   string  $message            APN result readable error message
         * @param   array   $infoDictionary     APN detailed info
         * @return  array   description of the error as dictionary
	 * @access  private
	*/
        protected function _reportMessage($code,$message,$infoDictionary=null) {
            $this->logMessage($message);
            return array(   "status_code"   =>  $code,
                            "message"       =>  $message,
                            "info"          =>  $infoDictionary);
        }
    }
   
?>
