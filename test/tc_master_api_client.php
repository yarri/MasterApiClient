<?php
class TcMasterApiClient extends TcBase {

	function test(){
		// Testing instance
		$client = new MasterApi_Client(array(
			"username" => "GR:TEST-JAROMIR-TOMEK-6",
			"password" => "EZA95RRq",
			"server_url" => "http://test-api.domainmaster.cz/masterapi/server.php",
		));

		$result = $client->sendCommand("show domain",array(
			"domain" => "domainmaster.cz"
		));
		$this->assertTrue($result->isSuccess());
		$domain_info = $result->getData();
		$this->assertEquals("domainmaster.cz",$domain_info["domain"]);

		$result = $client->sendCommand("credit info");
		$this->assertTrue($result->isSuccess());
		$credit_info = $result->getData();
		$this->assertEquals(array("CZK","EUR"),array_keys($credit_info));

		$result = $client->sendCommand("list domains");
		$this->assertTrue($result->isSuccess());
	}
}
