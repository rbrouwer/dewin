<?php

class Model_Stdout extends RedBean_SimpleModel {
	/**
	 * Simulates the terminal colors
	 * Format the input and returns as html with styles
	 * Function to be used with preg_replace.
	 * @param string $code
	 * @param string $value
	 * @return string The html tag with style
	 */
	private static function outputParserHelper($code, $value) {
		$attrs = explode(";", $code);

		if (sizeof($attrs) == 2 && intval($attrs[0]) > 10) {
			$attrs[2] = $attrs[1];
			$attrs[1] = $attrs[0];
		}

		if (sizeof($attrs) == 2 && intval($attrs[0]) == 0 && intval($attrs[1]) == 0) {
			$attrs[0] = 0;
			$attrs[1] = 37;
		}
		$text = array(
				'0' => '',
				'1' => 'font-weight:bold; ',
				'3' => 'text-decoration:underline; ',
				'5' => 'blink; '
		);
		$colors = array(
				'0' => 'black',
				'1' => 'red',
				'2' => '#89E234', // green
				'3' => 'yellow',
				'4' => '#729FCF', // blue
				'5' => 'magenta',
				'6' => 'cyan',
				'7' => 'white'
		);

		$text_decoration = (isset($attrs[0]) && array_key_exists(intval($attrs[0]), $text)) ? $text[intval($attrs[0])] : $text[0];
		$color = (isset($attrs[1]) && array_key_exists(intval($attrs[1]) - 30, $colors)) ? $colors[intval($attrs[1]) - 30] : $colors[0];
		$style = sprintf("%scolor:%s;", $text_decoration, $color);
		$style.= (isset($attrs[2]) && array_key_exists((intval($attrs[2]) - 40), $colors)) ? "background-color:" . $colors[(intval($attrs[2]) - 40)] : '';
		return "<tt style=\"$style\">$value</tt>";
	}

	/**
	 * Parse output to HTML.
	 * @param string $output
	 * @return mixed Returns false, if output shouldn't be visible. Otherwise returns output in HTML.
	 */
	public static function parseToHTML($output) {

		if (preg_match('/\x08/', $output))
			return false;

		$output = htmlentities($output, ENT_QUOTES);

		$output = explode("\n", $output);
		$output = implode("</span><span>", $output);
		$output = sprintf("<span>%s</span>", $output);
		$output = preg_replace("/\r\n|\r|\n/", '\n', $output);

		// Removes the first occurrence (on ls)
		$output = preg_replace('/\x1B\[0m(\x1B)/', "\x1B", $output);
		// Add colors to default coloring sytem
		$output = preg_replace('/\x1B\[([^m]+)m([^\x1B]+)\x1B\[0m/e', 'self::outputParserHelper(\'\\1\',\'\\2\')', $output);
		$output = preg_replace('/\x1B\[([^m]+)m([^\x1B]+)\x1B\[m/e', 'self::outputParserHelper(\'\\1\',\'\\2\')', $output);
		// Add colors to grep color system
		$output = preg_replace('/\x1B\[([^m]+)m\x1B\[K([^\x1B]+)\x1B\[m\x1B\[K/e', 'self::outputParserHelper("\\1","\\2")', $output);

		// Removes some dumb chars
		$output = preg_replace('/\x1B\[m/', '', $output);
		$output = preg_replace('/\x07/', '', $output);

		return $output;
	}
	
	public function getOutputWithoutColors() {
		return preg_replace('/\<tt style\="(.*?)"\>(.*?)\\<\/tt\>/', '$2', $this->output);
	}
}