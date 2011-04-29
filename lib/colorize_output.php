<?php

class TerminalColor {
	
	// values
	public static $yellow = "\033[1;33;40m";
	public static $red = "\033[1;31;40m";
	public static $green = "\033[1;32;40m";

	public static $reset = "\033[00m";
	
	// output
	public static function red() {
		echo self::$red;
	}
	public static function yellow() {
		echo self::$yellow;
	}
	public static function green() {
		echo self::$green;
	}
	
	public static function reset() {
		echo self::$reset;
	}
	
	public static function out($color, $string) {
		echo self::$$color.$string.self::$reset;
	}
}