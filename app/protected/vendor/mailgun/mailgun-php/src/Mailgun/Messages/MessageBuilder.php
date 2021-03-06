<?PHP

namespace Mailgun\Messages;

use Mailgun\Messages\Exceptions\InvalidParameter;
use Mailgun\Messages\Exceptions\TooManyParameters;
use Mailgun\Messages\Exceptions\InvalidParameterType;

/* 
   This class is used for composing a properly formed 
   message object. Dealing with arrays can be cumbersome, 
   this class makes the process easier. See the official 
   documentation for usage instructions. 
*/

class MessageBuilder{

	protected $message = array();
	protected $variables = array();
	protected $files = array();
	protected $sanitized;
	protected $toRecipientCount = 0;
	protected $ccRecipientCount = 0;
	protected $bccRecipientCount = 0;
	protected $attachmentCount = 0;
	protected $campaignIdCount = 0;
	protected $customOptionCount = 0;
	protected $tagCount = 0;
	
	protected function safeGet($params, $key, $default){
		if(array_key_exists($key, $params)){
			return $params[$key];
		}
		return $default;
	}
	
	protected function getFullName($params){		
		if(array_key_exists("first", $params)){
			$first = $this->safeGet($params, "first", "");
			$last = $this->safeGet($params, "last", "");
			return trim("$first $last");
		} 
		return $this->safeGet($params, "full_name", "");		
	}
	
	protected function parseAddress($address, $variables){
		if(!is_array($variables)){
			return $address;
		}
		$fullName = $this->getFullName($variables);
		if($fullName != null){
			return "'$fullName' <$address>";
		}
		return $address;
	}

	protected function addRecipient($headerName, $address, $variables){
		if($headerName == "to" && $this->toRecipientCount > RECIPIENT_COUNT_LIMIT){
			throw new TooManyParameters(TOO_MANY_PARAMETERS_RECIPIENT);
		}
		
		$compiledAddress = $this->parseAddress($address, $variables);

		if(isset($this->message[$headerName])){
			array_push($this->message[$headerName], $compiledAddress);
		}
		elseif($headerName == "h:reply-to"){
			$this->message[$headerName] = $compiledAddress;
		}
		else{
			$this->message[$headerName] = array($compiledAddress);
		}
		if($headerName == "to"){
			$this->toRecipientCount++;
		}
	}
	
	public function addToRecipient($address, $variables = null){
		$this->addRecipient("to", $address, $variables);
		return end($this->message['to']);
	}
	
	public function addCcRecipient($address, $variables = null){
		$this->addRecipient("cc", $address, $variables);
		return end($this->message['cc']);
	}

	public function addBccRecipient($address, $variables = null){
		$this->addRecipient("bcc", $address, $variables);
		return end($this->message['bcc']);
	}
	
	public function setFromAddress($address, $variables = null){
		$this->addRecipient("from", $address, $variables);
		return $this->message['from'];
	}
	
	public function setReplyToAddress($address, $variables = null){
		$this->addRecipient("h:reply-to", $address, $variables);
		return $this->message['h:reply-to'];
	}
	
	public function setSubject($subject = NULL){
		if($subject == NULL || $subject == ""){
			$subject = " ";
		}
		$this->message['subject'] = $subject;
		return $this->message['subject'];
	}
	
	public function addCustomHeader($headerName, $headerData){
		if(!preg_match("/^h:/i", $headerName)){
			$headerName = "h:" . $headerName;
		}
		$this->message[$headerName] = array($headerData);
		return $this->message[$headerName];
	}
	
	public function setTextBody($textBody){
		if($textBody == NULL || $textBody == ""){
			$textBody = " ";
		}
		$this->message['text'] = $textBody;
		return $this->message['text'];
	}
	
	public function setHtmlBody($htmlBody){
		if($htmlBody == NULL || $htmlBody == ""){
			$htmlBody = " ";
		}
		$this->message['html'] = $htmlBody;
		return $this->message['html'];
	}
	
	public function addAttachment($attachmentPath){
		if(preg_match("/^@/", $attachmentPath)){
			if(isset($this->files["attachment"])){
				array_push($this->files["attachment"], $attachmentPath);
				}	
				else{
					$this->files["attachment"] = array($attachmentPath);
				}
			return true;
		}
		else{
			throw new InvalidParameter(INVALID_PARAMETER_ATTACHMENT);
		}
	}
	
	public function addInlineImage($inlineImagePath){
		if(preg_match("/^@/", $inlineImagePath)){
			if(isset($this->files['inline'])){
				array_push($this->files['inline'] , $inlineImagePath);
				return true;
				}
			else{
				$this->files['inline'] = array($inlineImagePath);
				return true;
			}
		}
		else{
			throw new InvalidParameter(INVALID_PARAMETER_INLINE);
		}
	}
	
	public function setTestMode($testMode){
		if(filter_var($testMode, FILTER_VALIDATE_BOOLEAN)){
			$testMode = "yes";
		}
		else{
			$testMode = "no";
		}
		$this->message['o:testmode'] = $testMode;
		return $this->message['o:testmode'];
	}
	
	public function addCampaignId($campaignId){
		if($this->campaignIdCount < CAMPAIGN_ID_LIMIT){
			if(isset($this->message['o:campaign'])){
				array_push($this->message['o:campaign'] , $campaignId);
			}
			else{
				$this->message['o:campaign'] = array($campaignId);
			}
			$this->campaignIdCount++;
		return $this->message['o:campaign'];	
		}
		else{
			throw new TooManyParameters(TOO_MANY_PARAMETERS_CAMPAIGNS);
		}
	}
	
	public function addTag($tag){
		if($this->tagCount < TAG_LIMIT){
			if(isset($this->message['o:tag'])){
				array_push($this->message['o:tag'] , $tag);
			}
			else{
				$this->message['o:tag'] = array($tag);
			}
			$this->tagCount++;
		return $this->message['o:tag'];	
		}
		else{
			throw new TooManyParameters(TOO_MANY_PARAMETERS_TAGS);
		}
	}
	
	public function setDkim($enabled){
		if(filter_var($enabled, FILTER_VALIDATE_BOOLEAN)){
			$enabled = "yes";
		}
		else{
			$enabled = "no";
		}
		$this->message["o:dkim"] = $enabled;
		return $this->message["o:dkim"];
	}
	
	public function setOpenTracking($enabled){
		if(filter_var($enabled, FILTER_VALIDATE_BOOLEAN)){
			$enabled = "yes";
		}
		else{
			$enabled = "no";
		}
		$this->message['o:tracking-opens'] = $enabled;
		return $this->message['o:tracking-opens'];
	}
	
	public function setClickTracking($enabled){
		if(filter_var($enabled, FILTER_VALIDATE_BOOLEAN)){
			$enabled = "yes";
		}
		elseif($enabled == "html"){
			$enabled = "html";
		}
		else{
			$enabled = "no";
		}
		$this->message['o:tracking-clicks'] = $enabled;
		return $this->message['o:tracking-clicks'];
	}
	
	public function setDeliveryTime($timeDate, $timeZone = NULL){
		if(isset($timeZone)){
			$timeZoneObj = new \DateTimeZone("$timeZone");
		}
		else{
			$timeZoneObj = new \DateTimeZone(\DEFAULT_TIME_ZONE);
		}
		
		$dateTimeObj = new \DateTime($timeDate, $timeZoneObj);
		$formattedTimeDate = $dateTimeObj->format(\DateTime::RFC2822);
		$this->message['o:deliverytime'] = $formattedTimeDate;
		return $this->message['o:deliverytime'];
	}
	
	public function addCustomData($customName, $data){
		if(is_array($data)){
			$jsonArray = json_encode($data);
			$this->message['v:'.$customName] = $jsonArray;
			return $this->message['v:'.$customName];
		}
		else{
			throw new InvalidParameter(INVALID_PARAMETER_NON_ARRAY);
		}
		
	}
	
	public function addCustomParameter($parameterName, $data){
		if(isset($this->message[$parameterName])){
			array_push($this->message[$parameterName], $data);
			return $this->message[$parameterName];
		}
		else{
			$this->message[$parameterName] = array($data);
			return $this->message[$parameterName];
		}
	}

	public function getMessage(){
		return $this->message;
	}

	public function getFiles(){
		return $this->files;
	}
}
?>
