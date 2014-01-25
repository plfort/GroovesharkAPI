<?php
namespace plfort\GroovesharkAPI;

class GroovesharkException extends \Exception{

		private $gsCode;
		private $gsMmessage;

		public function __construct($error){
			if(is_array($error) && isset($error[0])){
				if(isset($error[0]['code'])){
					$this->gsCode = $error[0]['code'];
				}
				if(isset($error[0]['message'])){
					$this->gsMessage = $error[0]['message'];
				}
			}else{
				if(is_string($error)){
					$this->message = $error;
				}
			}
		}

	public function getGsCode() {
		return $this->gsCode;
	}
	public function getGsMmessage() {
		return $this->gsMmessage;
	}




}