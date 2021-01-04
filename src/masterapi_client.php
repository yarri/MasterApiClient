<?php
/**
 * Klient pro MasterAPI,
 * pomoci ktereho je mozne roboticky pristupovat k funkcim systemu www.DomainMaster.cz.
 * www.DomainMaster.cz provozuje spolecnost General Registry.
 *
 * Vice infomraci o MasterAPI naleznete na adrese:
 * http://www.domainmaster.cz/masterapi/
 */
class MasterApi_Client{
	var $_Username = "";
	var $_Password = "";

	var $_Charset = "UTF-8";

	var $_Host = "www.domainmaster.cz";
	var $_Uri = "/masterapi/server.php";
	var $_Port = 443;
	var $_Ssl = true;
	var $_DataFormat = "yaml"; // "yaml" nebo "json"

	/**
	 * Konstruktor.
	 *
	 *  $client = new MasterApi_Client(array(
	 *  	"username" => "GR:PLATCE",
	 *  	"password" => "secret",
	 *  	"charset" => "ISO-8859-2", // "UTF-8", "WINDOWS-1250", "ISO-8859-2",
	 *  	"server_url" => "https://www.domainmaster.cz/masterapi/server.php"
	 *  ));
	 *
	 * @access public
	 * @param array
	 */
	function MasterApi_Client($params = array()){
		$params = array_merge(array(
			"username" => "",
			"password" => "",
			"charset" => "UTF-8",
			"server_url" => "https://www.domainmaster.cz/masterapi/server.php",
			"data_format" => "yaml", // "yaml" nebo "json"
		),$params);

		$this->_Username = $params["username"];
		$this->_Password = $params["password"];
		$this->_Charset = $params["charset"];
		$this->_DataFormat = $params["data_format"];

		if(preg_match("/^http(s?):\\/\\/([^\\/:]+)(:([0-9]{1,6})|)(\\/.*)$/",$params["server_url"],$matches)){
			$this->_Ssl = ($matches[1]=="s");
			$this->_Host = $matches[2];
			if(strlen($matches[3])>0){
				$this->_Port = (int)$matches[4];
			}elseif($this->_Ssl){
				$this->_Port = 443;
			}else{
				$this->_Port = 80;
			}
			$this->_Uri = $matches[5];
		}
	}
	
	/**
	 * Odesle prikaz do Master API serveru.
	 *
	 *		// prikaz nevyzadujici autorizaci
	 *		$res = $client->sendCommand("register cz domain",array(
	 *			"domain" => "domenka.cz",
	 *			"registrant" => "ID-MAJITELE",
	 *			"admin" => array("ID-WEBHOSTER-1","ID-WEBHOSTER-2"),
	 *			"idacc" => "GR:PLATCE"
	 *		));
	 *
	 *		// prikaz vyzadujici autorizaci opravnenou osobou
	 *		$res = $client->sendCommand("update cd domain",array(
	 *			"domain" => "domenka.cz",
	 *			"nsset" => "ID-NSSET",
	 *		),array(
	 *			"contact" => "ID-WEBHOSTER-1",
	 *			"password" => "heslo kontaktu ID-WEBHOSTER-1"
	 *		));
	 * 
	 * @access public
	 * @param string $command					ie. "credit info"
	 * @param array $params						parametry prikazu (jsou-li potreba)
	 * @param array $authorization			kontakt a heslo potvrzujici zmenu (je-li potreba)
	 * @return MasterApi_ClientResult
	 */
	function sendCommand($command,$params = array(),$authorization = null){
		$ar = array(
			"command" => $command,
			"params" => $params
		);

		if($authorization){
			$ar["authorization"] = array_merge(array(
				"contact" => "",
				"password" => "",
				"contact_type" => "auto", // TODO: v budoucnu rozlisovat i jine typy kontakty
			),$authorization);
		}

		switch($this->_DataFormat){	
			case "json":
				$data = json_encode($ar);
				break;
			default:
				$data = miniYAML::Dump($ar);
		}

		$buff = array();
		$buff[] = "POST $this->_Uri HTTP/1.0";
		$buff[] = "Host: $this->_Host";
		$buff[] = "Content-Type: text/plain; charset=$this->_Charset";
		$buff[] = "Content-Length: ".strlen($data);
		$buff[] = "Authorization: Basic ".base64_encode("$this->_Username:$this->_Password");
		$buff[] = "";
		$buff[] = $data;
		$buff = join("\n",$buff);
		
		$_ssl = "";
		if($this->_Ssl){
			$_ssl = "ssl://";
		}
		if(!($f = fsockopen("$_ssl$this->_Host",$this->_Port,$errno,$errstr))){
			return new MasterApi_ClientResult(array(
				"http_request" => $buff,
				"network_error" => "can't open socket: $errstr ($errno)"
			));
		}
		if(!fwrite($f,$buff,strlen($buff))){
			return new MasterApi_ClientResult(array(
				"http_request" => $buff,
				"network_error" => "can't write to socket"
			));
		}
		$response = "";
		while($f && !feof($f)){
			$response .= fread($f,4096);
		}
		fclose($f);

		return new MasterApi_ClientResult(array(
			"http_request" => $buff,
			"http_response" => $response
		));
	}
}

/**
 * Trida pro zapouzdreni vysledku volani MasterApi_Client::sendCommand().
 *
 */
class MasterApi_ClientResult{
	var $_HttpRequest = null;
	var $_HttpResponseHeaders = null;
	var $_HttpResponseContent = null;

	var $_Result = null;

	var $_NetworkError = null; 

	function MasterApi_ClientResult($params){
		$params = array_merge(array(
			"http_request" => null,
			"http_response" => null,
			"network_error" => null,
		),$params);

		$this->_HttpRequest = $params["http_request"];
		$this->_NetworkError = $params["network_error"];

		if(preg_match("/^(.*?)\\r?\\n\\r?\\n(.*)$/s",$params["http_response"],$matches)){
			$this->_HttpResponseHeaders = $matches[1];
			$this->_HttpResponseContent = $matches[2];
		}
		if(preg_match('/^\s*{/s',$this->_HttpResponseContent)){ // autodetekce json
			$this->_Result = json_decode($this->_HttpResponseContent,true);
		}else{
			$this->_Result = miniYAML::Load($this->_HttpResponseContent);
		}
	}

	function isSuccess(){
		return (
			isset($this->_Result) &&
			$this->_Result["status"]=="success"
		);	
	}

	function isTemporaryError(){
		return (
			(isset($this->_Result) && $this->_Result["status"]=="temporary error") ||
			(isset($this->_NetworkError)) // nastane-li chyba na siti, jde o docasnou chybu
		);
	}

	function getMessage(){
		if(isset($this->_NetworkError)){
			return $this->_NetworkError;
		}
		if(!isset($this->_Result)){
			// pokud se nam podari vyzobnout HTTP response kode, vratime ho
			if(preg_match("/^HTTP\\/[0-9].[0-9] ([0-9]{3}.*)/",$this->_HttpResponseHeaders,$matches)){
				return "HTTP response code: $matches[1]";
			}
			return "response has not been successfuly parsed";
		}
		return $this->_Result["message"];
	}

	function getData(){
		if(isset($this->_Result) && isset($this->_Result["data"])){
			return $this->_Result["data"];
		}
		return null;
	}

	function getHttpRequest(){
		return $this->_HttpRequest;
	}

	function getHttpResponse(){
		if(isset($this->_HttpResponseHeaders)){
			return $this->_HttpResponseHeaders."\n\n".$this->_HttpResponseContent;
		}
		return null;
	}
}
