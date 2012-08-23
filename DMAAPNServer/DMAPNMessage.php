<?php

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

    /**
    * @category DMAPNMessage class. it represent a single message. A message can have one or more recipient.
    * @package  DMAPNPushServer
    * @author   Daniele Margutti <daniele.margutti@gmail.com>
    * @license  http://www.apache.org/licenses/LICENSE-2.0
    * @link     https://github.com/malcommac/DMAPNServer
    */

    /**
    * Begin Document
    */

 class DMAPNMessage {
        const   kAPPLE_RESERVED_NAMESPACE   = "aps";            // This namespace is reserved by Apple and you cannot use it directly
        const   kAPPLE_PAYLOAD_MAXLNEGTH    = 256;              // The maximum size allowed for a notification payload
        const   kDEVICE_BINARY_SIZE         = 32;               // Device token length
        const   kCOMMAND_PUSH               = 1;                // Payload command
        
        private $msg_destinationDevices     = array();          // destination UUIDs of the message
        private $msg_attachedProperties     = array();          // optional custom properties to attach
        private $msg_messageText            = "";               // Alert message to display to the user (not the title)
        private $msg_soundName              = "default";        // Name of the sound to play when user will receive this push message
        private $msg_badgeNumber            = 0;                // Number to badge the application icon with
        private $msg_autoTrimLongPayload    = true;             // If the JSON payload is longer than maximum allowed size, shorts message text
        private $msg_expirationTime         = 604800;           // This message will expire in <x> seconds if not successful delivered (default is 7 days)
        private $msg_messageIdentifier      = null;             // This used only for debug purpose inside the server program
        
         /**
	 * Constructor.
	 *
	 * Initializes a new message.
         * Don't confuse a message with a payload. Each message can have one or more recipient.
         * A payload will be generated at sending time.
         * 
	 * <code>
	 * <?php
	 * $message = new DMAPNMessage("first_destination_device","message text");
	 * ?>
 	 * </code>
	 *
	 * @param       string  $deviceUUID     first message recipient
         * @param       string  $message        content of the message
         * @return      DMAPNMessage            a new message class
	 * @access 	public
	 */
        function __construct($deviceUUID = null,$message = null) {
            if (isset($deviceUUID)) $this->addReceipient($deviceUUID);
            if (isset($message))    $this->msg_messageText = $message;
            $this->setSoundWithName();  // set default sound
            $this->setMessageIdentifier(uniqid(rand()), true);
        }
        
        /**
	 * Add a new recipient for this message
	 *
         * @param       string  $device_uuid          add a new recipient for this message
	 * @access      public
	 */
        public function addReceipient($device_uuid) {
            if (!preg_match('~^[a-f0-9]{64}$~i', $device_uuid))
                throw new DMAPNException("This is not a valid device uuid: '{$device_uuid}'");
            else $this->msg_destinationDevices[] = $device_uuid;
        }
        
        /**
	 * Remove a recipient at specified index
	 *
         * @param       int         $index          index of recipient to remove
         * @return      bool                        true if it was removed
	 * @access      public
	 */
        public function removeRecipientAtIndex($index) {
            if (!isset($this->msg_destinationDevices[$index])) {
                throw new DMAPNException("Invalid index '{$index}'. This message contains ".count($this->msg_destinationDevices)." recipients.");
                return false;
            } else {
                unset($this->msg_destinationDevices[$index]);
                return true;
            }
        }
        
        /**
	 * Return a recipient at index
	 *
         * @param       int         $index          index of recipient to get
         * @return      string                      destination device uuid
	 * @access      public
	 */
        public function recipientAtIndex($index=0) {
            if (!isset($this->msg_destinationDevices[$index])) {
                throw new DMAPNException("Invalid index '{$index}'. This message contains ".count($this->msg_destinationDevices)." recipients.");
                return null;
            } else
                return $this->msg_destinationDevices[$index];
        }
        
        /**
	 * Return the number of recipients set for this message
	 *
         * @return      int                      # recipients set
	 * @access      public
	 */
        public function countRecipients() {
            return count($this->msg_destinationDevices);
        }
        
        /**
	 * Return a list of recipients set for this message
	 *
         * @return      array                     messages's recipients
	 * @access      public
	 */
        public function recipients() {
            return $this->msg_destinationDevices;
        }
        
        
        /**
	 * Set the content of a message
	 *
         * @return      string         content of the message
	 * @access      public
	 */
        public function setMessageText($message) {
            $this->msg_messageText = $message;
        }
        
        
        /**
	 * Get the alert message to display to the user.
	 *
	 * @return @type string The alert message to display to the user.
	 */
        public function messageText() {
            return $this->msg_messageText;
        }
        
            /**
	 * Set the number to badge the application icon with.
	 *
	 * @param  $value @type integer A number to badge the application icon with.
	 */
        public function setBadgeValue($value=0) {
            if (!is_int($value))
                throw new DMAPNException("Badge value must be an integer (0 to unset it)");
            else $this->msg_badgeNumber = $value;
        }
        
        /**
	 * Get the number to badge the application icon with.
	 *
	 * @return @type integer The number to badge the application icon with.
	 */
        public function badgeValue() {
            return $this->msg_badgeNumber;
        }
        
        /**
	 * Set the sound to play.
	 *
	 * @param  $sSound @type string @optional A sound to play ('default' is
	 *         the default sound).
	 */
        public function setSoundWithName($soundName="default") {
            $this->msg_soundName = $soundName;
        }
        
        /**
	 * Get the sound to play.
	 *
	 * @return @type string The sound to play.
	 */
        public function soundName() {
            return $this->msg_soundName;
        }
        
        
	/**
	 * Set a custom property.
	 *
	 * @param  $propertyKey @type string Custom property key.
	 * @param  $propertyValue @type mixed Custom property value.
	 */
        public function setCustomPropertyWithKeyValue($propertyKey,$propertyValue) {
            $this->msg_attachedProperties[trim($propertyKey)] = $propertyValue;
        }
        
        /**
	 * Get the custom property value.
	 *
	 * @param  $propertyKey @type string Custom property key.
	 * @return @type string The custom property value.
	 */
        public function customPropertyForKey($propertyKey) {
            if (!array_key_exists($propertyKey, $this->msg_attachedProperties))
                throw new DMAPNException("No property exists with the specified name '{$propertyKey}'.");
            else
		return $this->msg_attachedProperties[$propertyKey];
        }
        
        /**
	 * Get all custom properties names.
	 *
	 * @return @type array All properties names.
	 */
        public function customPropertiesNames() {
            return array_keys($this->msg_attachedProperties);
        }
        
        	/**
	 * Set the auto-adjust long payload value.
	 *
	 * @param  $autoTrim @type boolean If true a long payload is shorted cutting long text value.
	 */
        public function setAutoTrimLongPayload($autoTrim=true) {
            $this->msg_autoTrimLongPayload = $autoTrim;
        }
        
        /**
	 * Get the auto-adjust long payload value.
	 *
	 * @return @type boolean The auto-adjust long payload value.
	 */
        public function autoTrimLongPayload() {
            return $this->msg_autoTrimLongPayload;
        }
        
       /**
	 * Set the message's expiry value.
	 *
	 * @param  $exp @type integer This message will expire in x seconds if not successful delivered.
	 */
        public function setMaxExpiryTime($exp=604800) {
            if (!is_int($exp))
                throw new DMAPNException("Invalid seconds number '{$exp}'");
            else
                $this->msg_expirationTime = $exp;
        }
        
        /**
	 * Get message's max expiry value.
	 *
	 * @return @type integer The expire message value (in seconds).
	 */
        public function maxExpiryTime() {
            return $this->msg_expirationTime;
        }
        
        protected function setMessageIdentifier($internalIdentifier) {
            $this->msg_messageIdentifier = $internalIdentifier;
        }
        
        public function messageIdentifier() {
            return $this->msg_messageIdentifier;
        }
        
        
	/**
	 * When an object is converted to a string, JSON representation of the payload is returned.
	 *
	 * @return @type string JSON representation of the payload.
         * @access public
	 */
        public  function __toString() {
            try {
                $JSONRepresentation = $this-payloadMessage();
            } catch (DMAPNException $exception) {
                $JSONRepresentation = null;
            }
            return $JSONRepresentation;
        }
        
        /**
	 * Get the payload dictionary.
	 *
	 * @return @type array The payload dictionary.
         * @access private
	 */
        protected function payloadMessageDictionary() {
            $aPayload[self::kAPPLE_RESERVED_NAMESPACE] = array();
            if (isset($this->msg_messageText))
                $aPayload[self::kAPPLE_RESERVED_NAMESPACE]['alert'] = (string) $this->msg_messageText;
            
            if (isset($this->msg_badgeNumber) && $this->msg_badgeNumber > 0)
                $aPayload[self::kAPPLE_RESERVED_NAMESPACE]['badge'] = (int)$this->msg_badgeNumber;
            
            if (isset($this->msg_soundName))
                $aPayload[self::kAPPLE_RESERVED_NAMESPACE]['sound'] = (string)$this->msg_soundName;
            
            foreach ($this->msg_attachedProperties as $propertyName => $propertyValue)
                $aPayload[$propertyName] = $propertyValue;
            
            return $aPayload;
        }
        
        /**
	 * Convert the message in a JSON-encoded payload.
	 *
         * 
	 * @return @type string JSON-encoded payload.
         * @access public
	 */
        public function payloadMessage() {
            $JSONPayload = str_replace(
			'"' . self::kAPPLE_RESERVED_NAMESPACE . '":[]',
			'"' . self::kAPPLE_RESERVED_NAMESPACE . '":{}',
			json_encode($this->payloadMessageDictionary())
            );
            
            $payload_length = strlen($JSONPayload);
            if ($JSONPayload > self::kAPPLE_PAYLOAD_MAXLNEGTH) {
                if ($this->msg_autoTrimLongPayload) {
                    // Try to trim it
                    $max_text_len = $nTextLen = strlen($this->msg_messageText) - ($payload_length - self::kAPPLE_PAYLOAD_MAXLNEGTH);
                    if ($max_text_len > 0) {
                        while (strlen($this->msg_messageText = mb_substr($this->msg_messageText, 0, --$nTextLen, 'UTF-8')) > $max_text_len);
                            return $this->getPayload();
                    } else {
                        throw new DMAPNException("Cannot auto-trim this message. It's too long: {$payload_length} bytes (max=" .self::PAYLOAD_MAXIMUM_SIZE . ").");
                    }
                } else {
                    throw new DMAPNException("JSON representation for this payload is too long: {$payload_length} bytes. Max size is " .self::kAPPLE_PAYLOAD_MAXLNEGTH . " bytes");
                }
            }
            
            return $JSONPayload;
        }
        
        /**
	 * Return a representation of the message for each recipient
	 *
         * 
	 * @return @type array a binary representation of the payload for each recipient
         * @access public
	 */
        public function payloadBinaryRepresentations() {
            $representations = array();
            $jsonpayload = $this->payloadMessage();
            foreach ($this->msg_destinationDevices as $deviceUUID)
                $representations[$deviceUUID] = $this->binaryRepresentationForDevice($deviceUUID,$jsonpayload);
            return $representations;
        }
        
          /**
	 * Return a representation of the message for one of it's destination device uuid
	 *
         * 
         * @param   @type string    $device_uuid        recipient (device unique identifier)
         * @param   @type string    $JSONPayload        binary representation of the payload
         * @param   @type string    $messageUniqueID    unique message id
         * @param   @type int       $expireTime         max message expire time
	 * @return  @type string  binary representation of the message with specified device uuid
         * @access public
	 */
        protected function binaryRepresentationForDevice($device_uuid,$JSONPayload,$messageUniqueID="",$expireTime = 604800) {
            // Specifications about payload generation are available here
            // http://tinyurl.com/payloadbinaryspecs
            
            // "For optimum performance, you should batch multiple notifications in a single transmission over the
            // interface, either explicitly or using a TCP/IP Nagle algorithm."

            // Simple notification format (Bytes: content.) :
            // 1: 0. 2: Token length. 32: Device Token. 2: Payload length. 34: Payload
            //      $msg = chr(0).pack("n",32).pack('H*',$token).pack("n",strlen($message)).$message;

            // Enhanced notification format: ("recommended for most providers")
            // 1: 1. 4: Identifier. 4: Expiry. 2: Token length. 32: Device Token. 2: Payload length. 34: Payload
            
            $payload_ength = strlen($JSONPayload);

            $sRet  = pack('CNNnH*', self::kCOMMAND_PUSH, $messageUniqueID, $expireTime > 0 ? time() + $expireTime : 0, self::kDEVICE_BINARY_SIZE, $device_uuid);
            $sRet .= pack('n', $payload_ength);
            $sRet .= $JSONPayload;

            return $sRet;
        }
    }
    


?>
