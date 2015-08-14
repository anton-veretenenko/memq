<?php

	// Fork: https://github.com/anton-veretenenko/memq
	if (!defined('_MEMCACHE_HOST_') || !defined('_MEMCACHE_PORT_')) {
		die("No Memcache server defined");
	}
	define('MEMQ_TTL', 0); // no expire for queue keys

	class Memq {
		
		private static $mem = null;
		
		private function __construct() {}
		
		private function __clone() {}
		
		private static function getInstance()
		{
			if(!self::$mem) {
				self::init();
			}

			return self::$mem;
		}
		
		private static function init()
		{
			$mem = new Memcached;
			$mem->addServer(_MEMCACHE_HOST_, _MEMCACHE_PORT_);
			
			self::$mem = $mem;
		}
		
		public static function is_empty($queue)
		{
			$mem = self::getInstance();
			$head = $mem->get($queue."_head");
			$tail = $mem->get($queue."_tail");
			
			if($head >= $tail || $head === false || $tail === false) {
				$mem->delete($queue."_head");
				$mem->delete($queue."_tail");
				return true;
			} else { 
				return false;
			}
		}

		public static function dequeue($queue)
		{
			$mem = self::getInstance();

			$tail = $mem->get($queue."_tail");
			if(($id = $mem->increment($queue."_head")) === false) {
				return false;
			}

			if($id <= $tail) {
				return $mem->get($queue."_".($id-1));
			} else {
				$mem->decrement($queue."_head");
				return false;
			}
		}
		
		public static function enqueue($queue, $item) {
			$mem = self::getInstance();
			
			$id = $mem->increment($queue."_tail");
			if($id === false) {
				if($mem->add($queue."_tail", 1, MEMQ_TTL) === false) {
					$id = $mem->increment($queue."_tail");
					if($id === false) {
						return false;
					}
				} else {
					$id = 1;
					$mem->add($queue."_head", $id, MEMQ_TTL);
				}
			}
			
			if($mem->set($queue."_".$id, $item, MEMQ_TTL) === false) {
				return false;
			}
			
			return $id;
		}
		
	}