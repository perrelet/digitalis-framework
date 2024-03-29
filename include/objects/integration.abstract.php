<?php

namespace Digitalis;

abstract class Integration extends Singleton {
	
	protected $loaded = false;
	
	public function __construct () {
		
		if (!$this->condition()) return false;

		$this->loaded = true;
		$this->run();
		
	}

	public function condition () {
        
        return true;
    
    }
    
	public function run () {}
	
	public function is_active () {
		
		return $this->loaded;
		
	}
	
}