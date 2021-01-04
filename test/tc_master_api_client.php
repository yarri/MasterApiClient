<?php
class TcMasterApiClient extends TcBase {

	function test(){
		$client = new MasterApi_Client();
		$this->assertTrue(!!$client);
	}
}
