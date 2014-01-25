<?php
namespace plfort\GroovesharkAPI;

class GroovesharkException extends \Exception{

		private $code;
		private $message;

		public function __construct($error){
			if(is_array($error) && isset($error[0])){
				if(isset($error[0]['code'])){
					$this->code = $error[0]['code'];
				}
				if(isset($error[0]['message'])){
					$this->message = $error[0]['message'];
				}
			}else{
				if(is_string($error)){
					$this->message = $error;
				}
			}
		}

}