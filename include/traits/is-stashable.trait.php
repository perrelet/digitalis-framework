<?php

namespace Digitalis;

trait Is_Stashable {

    public static function get_stash ($key) {
    
        return static::get_cache_store()->get($key, static::get_stash_group());
    
    }

    public static function pop_stash ($key) {

        if ($instance = static::get_stash($key)) $instance->unstash();
        return $instance;
    
    }

    public static function flush_stash () {
    
        return static::get_cache_store()->flush_group(static::get_stash_group());
    
    }

    protected static function get_cache_store () {

        global $wp_object_cache;
        return $wp_object_cache;

    }

    protected static function get_stash_group () {
    
        return static::class;
    
    }

    public function stash ($ttl = 300) {
    
        return static::get_cache_store()->set($this->get_cache_key(), $this, static::get_stash_group(), $ttl);
    
    }

    public function unstash () {
    
        return static::get_cache_store()->delete($this->get_cache_key(), static::get_stash_group());
    
    }

    abstract protected function get_cache_key ();

} 