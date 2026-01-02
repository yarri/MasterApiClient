<?php
class TcMasterApiClient extends TcBase {

	function test(){
		// Testing instance
		$client = new MasterApi_Client(array(
			"username" => "GR:TEST-JAROMIR-TOMEK-6",
			"password" => "EZA95RRq",
			"server_url" => "http://test-api.domainmaster.cz/masterapi/server.php",
		));

		// credit info
		$result = $client->sendCommand("credit info");
		$this->assertTrue($result->isSuccess(),print_r($result,true));
		$credit_info = $result->getData();
		$this->assertEquals(array("CZK","EUR"),array_keys($credit_info));

		// list domains
		$result = $client->sendCommand("list domains");
		$this->assertTrue($result->isSuccess(),print_r($result,true));
	}
}
