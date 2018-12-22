<?php
require_once(REL_DIR . 'tcpdf/tcpdf.php');

class MyTCPDF extends TCPDF {

	protected $start_y = -1;
	protected $start_page = -1;

	public function Header() {}

	public function Footer() {}

	public function getHeightStart() {
		// store current object
		$this->startTransaction();
		// store starting values
		$this->start_y = $this->GetY();
		$this->start_page = $this->getPage();
	}

	public function getHeightEnd() {
		// get the new Y
		$end_y = $this->GetY();
		$end_page = $this->getPage();
		// calculate height
		$height = 0;
		if ($end_page == $this->start_page) {
			$height = $end_y - $this->start_y;
		} else {
			for ($page = $this->start_page; $page <= $end_page; ++$page) {
				$this->setPage($page);
				if ($page == $this->start_page) {
					// first page
					$height += $this->h - $this->start_y - $this->bMargin;
				} elseif ($page == $end_page) {
					// last page
					$height += $end_y - $this->tMargin;
				} else {
					$height += $this->h - $this->tMargin - $this->bMargin;
				}
			}
		}
		// restore previous object
		$this->rollbackTransaction(true);
		$this->start_y = -1;
		$this->start_page = -1;
		return $height;
	}
}

function parse_munzee_url($url) {
	preg_match("#https?://www.munzee.com/(m/([^/]*)/([0-9]*)|g)/([A-Z0-9]*)/?#",
		$url, $matches);
	return $matches;
}

$formats = Array('A5', 'A4', 'A3', 'A2', 'LETTER');
$units = Array(
	'mm' => 'milimeters',
	'cm' => 'centimeters',
	'in' => 'inches',
);
$error_correction = Array(
	'L' => 'L (~7%)',
	'M' => 'M (~15%)',
	'Q' => 'Q (~25%)',
	'H' => 'H (~30%)',
);
$cut_lines = Array(
	'none' => 'None',
	'dashed' => 'Dashed line',
	'line' => 'Line',
);
$text_location = Array(
	'top' => 'Top',
	'bottom' => 'Bottom',
);
$text_align = Array(
	'L' => 'Left',
	'C' => 'Center',
	'R' => 'Right',
);

/* Default configuration */
$config = Array(
	'format' => 'A4',
	'units' => 'mm',
	'size' => 18,
	'padding' => 2,
	'error_correction' => "L",
	'text' => "230V",
	'text_align' => "C",
	'text_location' => "top",
	'background_color' => "#ffffff",
	'image' => "none",
	'color' => "#000000",
	'margin_left' => 5,
	'margin_top' => 5,
	'margin_right' => 5,
	'margin_bottom' => 5,
	'font_size' => 3,
	'show_nicknames' => 0,
	'show_numbers' => 0,
	'cut_lines' => "none"
);
if (isset($_COOKIE['print_config'])) {
	$v = json_decode($_COOKIE['print_config']);
	if (is_array((array)$v)) {
		foreach ($v as $k => $v) {
			$config[$k] = $v;
		}
	}
}


# We really can fit only 32 characters in the 25x25 QR code
# so it does not make sense to aim for smaller for normal codes

if (!empty($_POST)) {
	if (isset($_POST['format'])) {
		$config['format'] = $_POST['format'];
	}
	if (isset($_POST['units'])) {
		$config['units'] = $_POST['units'];
	}
	if (isset($_POST['size'])) {
		$config['size'] = $_POST['size'];
	}
	if (isset($_POST['padding'])) {
		$config['padding'] = $_POST['padding'];
	}
	if (isset($_POST['text'])) {
		$config['text'] = $_POST['text'];
	}
	if (isset($_POST['text_align'])) {
		$config['text_align'] = $_POST['text_align'];
	}
	if (isset($_POST['text_location'])) {
		$config['text_location'] = $_POST['text_location'];
	}
	if (isset($_POST['error_correction'])) {
		$config['error_correction'] = $_POST['error_correction'];
	}
	if (isset($_POST['background_color'])) {
		$config['background_color'] = $_POST['background_color'];
	}
	if (isset($_POST['image'])) {
		$config['image'] = $_POST['image'];
	}
	if (isset($_POST['color'])) {
		$config['color'] = $_POST['color'];
	}
	if (isset($_POST['margin_left'])) {
		$config['margin_left'] = $_POST['margin_left'];
	}
	if (isset($_POST['margin_top'])) {
		$config['margin_top'] = $_POST['margin_top'];
	}
	if (isset($_POST['margin_right'])) {
		$config['margin_right'] = $_POST['margin_right'];
	}
	if (isset($_POST['margin_bottom'])) {
		$config['margin_bottom'] = $_POST['margin_bottom'];
	}
	if (isset($_POST['font_size'])) {
		$config['font_size'] = $_POST['font_size'];
	}
	if (isset($_POST['show_nicknames'])) {
		$config['show_nicknames'] = ($_POST['show_nicknames'] == 'on');
	}
	if (isset($_POST['show_numbers'])) {
		$config['show_numbers'] = ($_POST['show_numbers'] == 'on');
	}
	if (isset($_POST['cut_lines'])) {
		$config['cut_lines'] = $_POST['cut_lines'];
	}
	/* Store the cookie to expire in 1 year */
	setcookie('print_config', json_encode($config), time()+365*24*60*60);

	$codes = Array();
	if (isset($_POST['codes'])) {
		$codes = explode("\n", $_POST['codes']);
	}
	if (empty($codes) || count($codes) < 1) {
		print "Missing codes";
		exit;
	}

	/* For the QR code */
	$style = Array(
	);

	$pdf = new MyTCPDF('P', $config['units'], $config['format'], true, 'UTF-8', false);
	$pdf->SetFontSize($config['font_size'] / 0.35);
	$color = $config['color'];
	if ($color && $color[0] == '#') {
		$r = hexdec(substr($color, 1, 2));
		$g = hexdec(substr($color, 3, 2));
		$b = hexdec(substr($color, 5, 2));
		if ($r >= 0 && $r < 256 &&
			$g >= 0 && $g < 256 &&
			$b >= 0 && $b < 256) {
			$pdf->SetTextColor($r, $g, $b);
			$style['fgcolor'] = Array($r, $g, $b);
		}
	}

	$fill = false;
	$code_margin_top = 0;
	$code_margin_bottom = 0;
	$code_margin_left = 0;
	$code_margin_right = 0;
	if ($config['image'] == 'none') {
		$fill = true;
		$background_color = $config['background_color'];
		if ($background_color && $background_color[0] == '#') {
			$r = hexdec(substr($background_color, 1, 2));
			$g = hexdec(substr($background_color, 3, 2));
			$b = hexdec(substr($background_color, 5, 2));
			if ($r >= 0 && $r < 256 &&
				$g >= 0 && $g < 256 &&
				$b >= 0 && $b < 256) {
				$pdf->SetFillColor($r, $g, $b);
				$style['bgcolor'] = Array($r, $g, $b);
			}
		}
	} elseif ($config['image'] == 'electro') {
		/* This image does not have any place for text around the code.
		 * Just hardcode some margins */
		unset($config['text']);
		$config['show_numbers'] = 0;
		$config['show_nicknames'] = 0;
		$image_path = 'images/electro.png';
		$code_margin_top = $config['size'] + $config['size'] / 2;
		$code_margin_bottom = $config['size'] / 4;
		$code_margin_left = $config['size'] / 4;
		$code_margin_right = $config['size'] / 4;
	}

	$border_style = NULL;
	if ($config['cut_lines'] == 'dashed') {
		$border_style = Array('dash' => '1,2');
	} elseif ($config['cut_lines'] == 'line') {
		$border_style = Array('dash' => 0);
	}
	$t = '';
	$border_height = $config['size'] + $code_margin_top + $code_margin_bottom;
	if (empty($config['text']) || $config['text_location'] == 'bottom') {
		$t = 'T';
		$border_height += $config['padding'];
	}
	if (empty($config['text']) || $config['text_location'] == 'top') {
		if (!$config['show_numbers'] && !$config['show_nicknames']) {
			$t = $t . 'B';
			$border_height += $config['padding'];
		}
	}
	$border_location = $t . 'LR';

	$pdf->setCellPaddings($config['padding'], 0, $config['padding'], 0);
	$pdf->SetMargins($config['margin_left'], $config['margin_top'], $config['margin_right']);
	$pdf->SetAutoPageBreak(true, $config['margin_bottom']);
	$pdf->AddPage();

	$cell_width = $config['size'] + $config['padding'] * 2 + $code_margin_left + $code_margin_right;

	/* Get the text height, if defined */
	$pdf->getHeightStart();
	if (!empty($config['text'])) {
		// This is how our string will look like
		$pdf->MultiCell($cell_width, 0, $config['text'], 0, $config['text_align'], false, 2);
	}
	if ($config['show_numbers'] || $config['show_nicknames']) {
		$m = parse_munzee_url($codes[0]);
		if ($m) {
			$v = '';
			if (isset($m[2]) && $config['show_nicknames']) {
				$v .= $m[2];
			}
			if (isset($m[3]) && $config['show_numbers']) {
				$v .= ' #' . $m[3];
			}
			$pdf->MultiCell($cell_width, 0, $v, 0, $config['text_align'], false, 2);
		}
	}
	$text_height = $pdf->getHeightEnd();

	$cell_height = $config['size'] + $text_height + $config['padding'] * 2 + $code_margin_top + $code_margin_bottom;



	/* Create the page */
	$dim = $pdf->getPageDimensions();
	$width = $dim['w'];
	foreach ($codes AS $c) {
		$c = trim($c);
		if (empty($c)) {
			continue;
		}
		/* Do we fit this line onto this page? */
		if (($pdf->getY() + $cell_height) > 0.35 * ($dim['h'] - $dim['tm'] - $dim['bm'])) {
			$pdf->AddPage();
		}
		/* This is where we start */
		$y = $pdf->getY();
		$x = $pdf->getX();

		/* Background image */
		if (isset($image_path)) {
			$w = $config['size'] + $code_margin_left + $code_margin_right;
			$h = $config['size'] + $code_margin_top + $code_margin_bottom;
			$pdf->Image($image_path, $x + $config['padding'],
				$y + $config['padding'], $w, $h, 'PNG');
			$pdf->setY($y);
			$pdf->setX($x);
		}

		/* Additional text on top */
		if (!empty($config['text']) && $config['text_location'] == 'top') {
			$border = $border_style ? Array('LTR' => $border_style) : 0;
			$pdf->setCellPaddings('', $config['padding'], '', 0);
			$pdf->MultiCell($cell_width, 0, $config['text'], $border,
				$config['text_align'], true, 2);
			$pdf->setX($x);
			$barcode_y = $pdf->getY();
		} else {
			$barcode_y = $pdf->getY() + $config['padding'];
		}
		/* Draw a possible border */
		$border = 0;
		if ($border_style) {
			$border = Array($border_location => $border_style);
		}
		$pdf->Cell($cell_width, $border_height, '', $border, 0, 'L', $fill);
		$pdf->setX($x);

		/* This is internal margin of the attached image */
		$pdf->setY($barcode_y + $code_margin_top);
		$pdf->setX($x + $code_margin_left + $config['padding']);

		/* The barcode */
		$s = $config['size'];
		$pdf->write2DBarcode($c, "QRCODE,L", '', '', $s, $s, $style, 'T', false);
		$pdf->setY($barcode_y + $s);
		$pdf->setX($x);

		/* Additional text on bottom */
		if (!empty($config['text']) && $config['text_location'] == 'bottom') {
			$border = Array('LRB' => $border_style);
			$pdf->setCellPaddings('', 0, '', $config['padding']);
			if ($config['show_numbers'] || $config['show_nicknames']) {
				$border = Array('LR' => $border_style);
			} else {
				$pdf->setCellPaddings('', '', '', 0);
			}
			$border = $border_style ? $border : 0;
			$pdf->MultiCell($cell_width, 0, $config['text'], $border,
				$config['text_align'], true, 2);
			$pdf->setX($x);
		}
		if ($config['show_numbers'] || $config['show_nicknames']) {
			$m = parse_munzee_url($c);
			if ($m) {
				$v = '';
				if (isset($m[2]) && $config['show_nicknames']) {
					$v .= $m[2];
				}
				if ($config['show_nicknames'] && $config['show_numbers']) {
					$v .= ' ';
				}
				if (isset($m[3]) && $config['show_numbers']) {
					$v .= '#' . $m[3];
				}
				$border = $border_style ? Array('LRB' => $border_style) : 0;
				$pdf->setCellPaddings('', 0, '', $config['padding']);
				$pdf->MultiCell($cell_width, 0, $v, $border,
					$config['text_align'], true, 2);
			}
		}
		$pdf->setY($y);
		$pdf->setX($x + $cell_width);
		if (($pdf->getX() + $cell_width) > 0.35 * ($width - $dim['lm'] - $dim['rm'])) {
			$pdf->Ln($cell_height);
		}
	}

	$pdf->Output('munzee.pdf', 'I');
	exit;
}

?><!DOCTYPE html>
<html>
<head>
	<style>
fieldset {
    width: 300px;
    float: left;
}
input[type="number"] {
	width: 100px;
}
	</style>
</head>
<body>
<body>
	<h1>Munzee print tool</h1>
	<form action="" method="POST">
		<fieldset>
			<legend>Page configuration</legend>
			<label for="format">Page format:</label>
			<select name="format" id="format">
				<?php
				foreach ($formats AS $f) {
					print '<option value="' . $f . '"'
						. ($config['format'] == $f ? ' selected="selected"' : '')
						. '>' . $f . '</option>';
				}
				?>
			</select>
			<br />
			<label for="units">Units:</label>
			<select name="units" id="units">
				<?php
				foreach ($units AS $f => $label) {
					print '<option value="' . $f . '"'
						. ($config['units'] == $f ? ' selected="selected"' : '')
						. '>' . $label . '</option>';
				}
				?>
			</select>
			<br />
			<label for="margin_left">Margin left:</label>
			<input type="number" name="margin_left" id="margin_left"
				value="<?php echo $config['margin_left']; ?>" />
			<br />
			<label for="margin_right">Margin right:</label>
			<input type="number" name="margin_right" id="margin_right"
				value="<?php echo $config['margin_right']; ?>" />
			<br />
			<label for="margin_top">Margin top:</label>
			<input type="number" name="margin_top" id="margin_top"
				value="<?php echo $config['margin_top']; ?>" />
			<br />
			<label for="margin_bottom">Margin bottom:</label>
			<input type="number" name="margin_bottom" id="margin_bottom"
				value="<?php echo $config['margin_bottom']; ?>" />
		</fieldset>
		<fieldset>
			<legend>QR Code configuration</legend>
			<label for="size">QR code size:</label>
			<input type="number" name="size" id="size"
				value="<?php echo $config['size']; ?>" />
			<br />
			<label for="padding">Padding:</label>
			<input type="number" name="padding" id="padding"
				value="<?php echo $config['padding']; ?>" />
			<br />
			<label for="error_correction">Error correction:</label>
			<select name="error_correction" id="error_correction">
				<?php
				foreach ($error_correction AS $f => $label) {
					print '<option value="' . $f . '"'
						. ($config['error_correction'] == $f ? ' selected="selected"' : '')
						. '>' . $label . '</option>';
				}
				?>
			</select><br />
			<label for="cut_lines">Cut lines:</label>
			<select name="cut_lines" id="cut_lines">
				<?php
				foreach ($cut_lines AS $f => $label) {
					print '<option value="' . $f . '"'
						. ($config['cut_lines'] == $f ? ' selected="selected"' : '')
						. '>' . $label . '</option>';
				}
				?>
			</select>
		</fieldset>
		<fieldset>
			<legend>Things around</legend>
			<label for="text">Text:</label>
			<input type="text" name="text" id="text"
				value="<?php echo $config['text']; ?>" />
			<br />
			<label for="text">Text align:</label>
			<select name="text_align" id="text_align">
				<?php
				foreach ($text_align AS $f => $label) {
					print '<option value="' . $f . '"'
						. ($config['text_align'] == $f ? ' selected="selected"' : '')
						. '>' . $label . '</option>';
				}
				?>
			</select>
			<br />
			<label for="text_location">Text location:</label>
			<select name="text_location" id="text_location">
				<?php
				foreach ($text_location AS $f => $label) {
					print '<option value="' . $f . '"'
						. ($config['text_location'] == $f ? ' selected="selected"' : '')
						. '>' . $label . '</option>';
				}
				?>
			</select>
			<br />
			<label for="font_size">Font size:</label>
			<input type="number" name="font_size" id="font_size"
				value="<?php echo $config['font_size']; ?>" />
			<br />
			<label for="color">Text/code color:</label>
			<input type="color" name="color" id="color"
				value="<?php echo $config['color']; ?>" />
			<br />
			<label for="show_numbers">Show numbers:</label>
			<input type="checkbox" name="show_numbers" id="show_numbers"<?php
				if ($config['show_numbers']) { echo ' checked="checked"'; }
				?>>
			<br />
			<label for="show_nicknames">Show nicknames:</label>
			<input type="checkbox" name="show_nicknames" id="show_nicknames"<?php
				if ($config['show_nicknames']) { echo ' checked="checked"'; }
				?>>
		</fieldset>
		<fieldset>
			<legend>Background image or color</legend>
			<input type="radio" name="image" id="image_none" value="none"<?php
				if ($config['image'] == 'none') { echo ' checked="checked"'; } ?> />
			<label for="image_none">Color:</label>
			<input type="color" name="background_color" id="background_color"
				value="<?php echo $config['background_color']; ?>" />
			<input type="radio" name="image" id="image_electro" value="electro"<?php
				if ($config['image'] == 'electro') { echo ' checked="checked"'; } ?> />
			<label for="image_electro"><img src="images/electro.png" width="50" /></label>
		</fieldset>
		<fieldset>
			<legend>Munzee Codes:</legend>
			<textarea name="codes" cols="50" rows="10"></textarea>
			<button type="submit">Save to PDF</button>
		</fieldset>
	</form>

	<div style="clear:both"></div>
	<h2>How to get the codes?</h2>
	<p>The MunzeePrint extension for TamperMonkey can be downloaded here (without ads): <a href="https://munzee.dta3.com/MunzeePrint.js">Install MunzePrint</a>.
	Then navigate to <a href="https://www.munzee.com/print">https://www.munzee.com/print</a> and get your codes.</p>

	<h2>Something does not work? I want to improve it?</h2>
	<p>The source code in on github. Please, report any issues <a href="https://github.com/Jakuje/MunzeePrint">there</a> as well as any contributions are always welcomed.</p>

	<p>Author: <a href="https://jakuje.dta3.com/about.phtml">Jakuje</a></p>
</body>
</html>
