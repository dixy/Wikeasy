<?php
// $Id: DiffEngine.php,v 1.4.2.1.2.1 2008/04/08 14:01:57 weitzman Exp $

/**
 * A PHP diff engine for phpwiki. (Taken from phpwiki-1.3.3)
 *
 * Copyright (C) 2000, 2001 Geoffrey T. Dairiki <dairiki@dairiki.org>
 * You may copy this code freely under the conditions of the GPL.
 */

define('USE_ASSERTS', FALSE);

class _DiffOp {
	var $type;
	var $orig;
	var $closing;

	function reverse() { trigger_error('pure virtual', E_USER_ERROR); }
	function norig() { return $this->orig ? sizeof($this->orig) : 0; }
	function nclosing() { return $this->closing ? sizeof($this->closing) : 0; }
}

class _DiffOp_Copy extends _DiffOp {
	var $type = 'copy';
	function _DiffOp_Copy($orig, $closing = FALSE) {
		if (!is_array($closing)) { $closing = $orig; }
		$this->orig = $orig;
		$this->closing = $closing; }

	function reverse() { return new _DiffOp_Copy($this->closing, $this->orig); }
}

class _DiffOp_Delete extends _DiffOp { 
	var $type = 'delete';
	function _DiffOp_Delete($lines) { $this->orig = $lines; $this->closing = FALSE; }
	function reverse() { return new _DiffOp_Add($this->orig); }
}

class _DiffOp_Add extends _DiffOp {
	var $type = 'add';
	function _DiffOp_Add($lines) { $this->closing = $lines; $this->orig = FALSE; }
	function reverse() { return new _DiffOp_Delete($this->closing); }
}

class _DiffOp_Change extends _DiffOp {
	var $type = 'change';
	function _DiffOp_Change($orig, $closing) { $this->orig = $orig; $this->closing = $closing; }
	function reverse() { return new _DiffOp_Change($this->closing, $this->orig); }
}

class _DiffEngine {
	function MAX_XREF_LENGTH() { return 10000; }

	function diff($from_lines, $to_lines) {
		$n_from = sizeof($from_lines);
		$n_to = sizeof($to_lines);

		$this->xchanged = $this->ychanged = array();
		$this->xv = $this->yv = array();
		$this->xind = $this->yind = array();
		unset($this->seq);
		unset($this->in_seq);
		unset($this->lcs);

		for ($skip = 0; $skip < $n_from && $skip < $n_to; $skip++) {
			if ($from_lines[$skip] !== $to_lines[$skip]) { break; }
			$this->xchanged[$skip] = $this->ychanged[$skip] = FALSE;
		}

		$xi = $n_from;
		$yi = $n_to;
		for ($endskip = 0; --$xi > $skip && --$yi > $skip; $endskip++) {
			if ($from_lines[$xi] !== $to_lines[$yi]) { break; }
			$this->xchanged[$xi] = $this->ychanged[$yi] = FALSE;
		}

		for ($xi = $skip; $xi < $n_from - $endskip; $xi++) { $xhash[$this->_line_hash($from_lines[$xi])] = 1; }

		for ($yi = $skip; $yi < $n_to - $endskip; $yi++) {
			$line = $to_lines[$yi];
			if ($this->ychanged[$yi] = empty($xhash[$this->_line_hash($line)])) { continue;	}
			$yhash[$this->_line_hash($line)] = 1;
			$this->yv[] = $line;
			$this->yind[] = $yi;
		}
		for ($xi = $skip; $xi < $n_from - $endskip; $xi++) {
			$line = $from_lines[$xi];
			if ($this->xchanged[$xi] = empty($yhash[$this->_line_hash($line)])) {
				continue;
			}
			$this->xv[] = $line;
			$this->xind[] = $xi;
		}

		$this->_compareseq(0, sizeof($this->xv), 0, sizeof($this->yv));

		$this->_shift_boundaries($from_lines, $this->xchanged, $this->ychanged);
		$this->_shift_boundaries($to_lines, $this->ychanged, $this->xchanged);

		$edits = array();
		$xi = $yi = 0;
		while ($xi < $n_from || $yi < $n_to) {
			USE_ASSERTS && assert($yi < $n_to || $this->xchanged[$xi]);
			USE_ASSERTS && assert($xi < $n_from || $this->ychanged[$yi]);

			$copy = array();
			while ( $xi < $n_from && $yi < $n_to && !$this->xchanged[$xi] && !$this->ychanged[$yi]) {
				$copy[] = $from_lines[$xi++];
				++$yi;
			}
			if ($copy) { $edits[] = new _DiffOp_Copy($copy); }
			$delete = array();
			while ($xi < $n_from && $this->xchanged[$xi]) { $delete[] = $from_lines[$xi++]; }
			$add = array();
			while ($yi < $n_to && $this->ychanged[$yi]) { $add[] = $to_lines[$yi++]; }
			if ($delete && $add) { $edits[] = new _DiffOp_Change($delete, $add); }
			elseif ($delete) { $edits[] = new _DiffOp_Delete($delete); }
			elseif ($add) { $edits[] = new _DiffOp_Add($add); }
		}
		return $edits;
	}

	function _line_hash($line) {
		if (strlen($line) > $this->MAX_XREF_LENGTH()) { return md5($line); }
		else { return $line; }
	}

	function _diag($xoff, $xlim, $yoff, $ylim, $nchunks) {
		$flip = FALSE;

		if ($xlim - $xoff > $ylim - $yoff) { 
			$flip = TRUE;
			list($xoff, $xlim, $yoff, $ylim) = array($yoff, $ylim, $xoff, $xlim); }
		if ($flip) { for ($i = $ylim - 1; $i >= $yoff; $i--) { $ymatches[$this->xv[$i]][] = $i; } }
		else { for ($i = $ylim - 1; $i >= $yoff; $i--) { $ymatches[$this->yv[$i]][] = $i; } }
		$this->lcs = 0;
		$this->seq[0]= $yoff - 1;
		$this->in_seq = array();
		$ymids[0] = array();

		$numer = $xlim - $xoff + $nchunks - 1;
		$x = $xoff;
		for ($chunk = 0; $chunk < $nchunks; $chunk++) {
			if ($chunk > 0) {
				for ($i = 0; $i <= $this->lcs; $i++) {
					$ymids[$i][$chunk-1] = $this->seq[$i];
				}
			}

			$x1 = $xoff + (int)(($numer + ($xlim-$xoff)*$chunk) / $nchunks);
			for ( ; $x < $x1; $x++) {
				$line = $flip ? $this->yv[$x] : $this->xv[$x];
				if (empty($ymatches[$line])) {
					continue;
				}
				$matches = $ymatches[$line];
				reset($matches);
				while (list ($junk, $y) = each($matches)) {
					if (empty($this->in_seq[$y])) {
						$k = $this->_lcs_pos($y);
						USE_ASSERTS && assert($k > 0);
						$ymids[$k] = $ymids[$k-1];
						break;
					}
				}
				while (list ($junk, $y) = each($matches)) {
					if ($y > $this->seq[$k-1]) {
						USE_ASSERTS && assert($y < $this->seq[$k]);
						$this->in_seq[$this->seq[$k]] = FALSE;
						$this->seq[$k] = $y;
						$this->in_seq[$y] = 1;
					}
					elseif (empty($this->in_seq[$y])) {
						$k = $this->_lcs_pos($y);
						USE_ASSERTS && assert($k > 0);
						$ymids[$k] = $ymids[$k-1];
					}
				}
			}
		}

		$seps[] = $flip ? array($yoff, $xoff) : array($xoff, $yoff);
		$ymid = $ymids[$this->lcs];
		for ($n = 0; $n < $nchunks - 1; $n++) {
			$x1 = $xoff + (int)(($numer + ($xlim - $xoff) * $n) / $nchunks);
			$y1 = $ymid[$n] + 1;
			$seps[] = $flip ? array($y1, $x1) : array($x1, $y1);
		}
		$seps[] = $flip ? array($ylim, $xlim) : array($xlim, $ylim);

		return array($this->lcs, $seps);
	}

	function _lcs_pos($ypos) {
		$end = $this->lcs;
		if ($end == 0 || $ypos > $this->seq[$end]) {
			$this->seq[++$this->lcs] = $ypos;
			$this->in_seq[$ypos] = 1;
			return $this->lcs;
		}

		$beg = 1;
		while ($beg < $end) {
			$mid = (int)(($beg + $end) / 2);
			if ($ypos > $this->seq[$mid]) {
				$beg = $mid + 1;
			}
			else {
				$end = $mid;
			}
		}

		USE_ASSERTS && assert($ypos != $this->seq[$end]);

		$this->in_seq[$this->seq[$end]] = FALSE;
		$this->seq[$end] = $ypos;
		$this->in_seq[$ypos] = 1;
		return $end;
	}

	function _compareseq($xoff, $xlim, $yoff, $ylim) {
		while ($xoff < $xlim && $yoff < $ylim && $this->xv[$xoff] == $this->yv[$yoff]) {
			++$xoff;
			++$yoff;
		}

		while ($xlim > $xoff && $ylim > $yoff && $this->xv[$xlim - 1] == $this->yv[$ylim - 1]) {
			--$xlim;
			--$ylim;
		}

		if ($xoff == $xlim || $yoff == $ylim) {	$lcs = 0; }
		else {
			$nchunks = min(7, $xlim - $xoff, $ylim - $yoff) + 1;
			list($lcs, $seps)
			= $this->_diag($xoff, $xlim, $yoff, $ylim, $nchunks);
		}

		if ($lcs == 0) {
			while ($yoff < $ylim) {
				$this->ychanged[$this->yind[$yoff++]] = 1;
			}
			while ($xoff < $xlim) {
				$this->xchanged[$this->xind[$xoff++]] = 1;
			}
		}
		else {
			reset($seps);
			$pt1 = $seps[0];
			while ($pt2 = next($seps)) {
				$this->_compareseq ($pt1[0], $pt2[0], $pt1[1], $pt2[1]);
				$pt1 = $pt2;
			}
		}
	}

	function _shift_boundaries($lines, &$changed, $other_changed) {
		$i = 0;
		$j = 0;

		USE_ASSERTS && assert('sizeof($lines) == sizeof($changed)');
		$len = sizeof($lines);
		$other_len = sizeof($other_changed);

		while (1) {
			while ($j < $other_len && $other_changed[$j]) { $j++; }
			while ($i < $len && ! $changed[$i]) {
				USE_ASSERTS && assert('$j < $other_len && ! $other_changed[$j]');
				$i++;
				$j++;
				while ($j < $other_len && $other_changed[$j]) { $j++; }
			}

			if ($i == $len) { break; }
			$start = $i;

			while (++$i < $len && $changed[$i]) { continue; }
			
			do {
				$runlength = $i - $start;

				while ($start > 0 && $lines[$start - 1] == $lines[$i - 1]) {
					$changed[--$start] = 1;
					$changed[--$i] = FALSE;
					while ($start > 0 && $changed[$start - 1]) {
						$start--;
					}
					USE_ASSERTS && assert('$j > 0');
					while ($other_changed[--$j]) {
						continue;
					}
					USE_ASSERTS && assert('$j >= 0 && !$other_changed[$j]');
				}

				$corresponding = $j < $other_len ? $i : $len;

				while ($i < $len && $lines[$start] == $lines[$i]) {
					$changed[$start++] = FALSE;
					$changed[$i++] = 1;
					while ($i < $len && $changed[$i]) {
						$i++;
					}
					USE_ASSERTS && assert('$j < $other_len && ! $other_changed[$j]');
					$j++;
					if ($j < $other_len && $other_changed[$j]) {
						$corresponding = $i;
						while ($j < $other_len && $other_changed[$j]) {
							$j++;
						}
					}
				}
			} while ($runlength != $i - $start);

			while ($corresponding < $i) {
				$changed[--$start] = 1;
				$changed[--$i] = 0;
				USE_ASSERTS && assert('$j > 0');
				while ($other_changed[--$j]) {
					continue;
				}
				USE_ASSERTS && assert('$j >= 0 && !$other_changed[$j]');
			}
		}
	}
}

class Diff {
	var $edits;

	function Diff($from_lines, $to_lines) { $eng = new _DiffEngine; $this->edits = $eng->diff($from_lines, $to_lines); }

	function reverse() {
		$rev = $this;
		$rev->edits = array();
		foreach ($this->edits as $edit) {
			$rev->edits[] = $edit->reverse();
		}
		return $rev;
	}

	function isEmpty() {
		foreach ($this->edits as $edit) { if ($edit->type != 'copy') { return FALSE; } }
		return TRUE;
	}

	function lcs() {
		$lcs = 0;
		foreach ($this->edits as $edit) { if ($edit->type == 'copy') { $lcs += sizeof($edit->orig); } }
		return $lcs;
	}

	function orig() {
		$lines = array();
		foreach ($this->edits as $edit) { if ($edit->orig) { array_splice($lines, sizeof($lines), 0, $edit->orig); } }
		return $lines;
	}

	function closing() {
		$lines = array();
		foreach ($this->edits as $edit) { if ($edit->closing) { array_splice($lines, sizeof($lines), 0, $edit->closing); } }
		return $lines;
	}
	
	function _check($from_lines, $to_lines) {
		if (serialize($from_lines) != serialize($this->orig())) {
			trigger_error("Reconstructed original doesn't match", E_USER_ERROR);
		}
		if (serialize($to_lines) != serialize($this->closing())) {
			trigger_error("Reconstructed closing doesn't match", E_USER_ERROR);
		}

		$rev = $this->reverse();
		if (serialize($to_lines) != serialize($rev->orig())) {
			trigger_error("Reversed original doesn't match", E_USER_ERROR);
		}
		if (serialize($from_lines) != serialize($rev->closing())){ trigger_error("Reversed closing doesn't match", E_USER_ERROR);}


		$prevtype = 'none';
		foreach ($this->edits as $edit) {
			if ( $prevtype == $edit->type ) {
				trigger_error("Edit sequence is non-optimal", E_USER_ERROR);
			}
			$prevtype = $edit->type;
		}

		$lcs = $this->lcs();
		trigger_error('Diff okay: LCS = '. $lcs, E_USER_NOTICE);
	}
}

class MappedDiff extends Diff {
	function MappedDiff($from_lines, $to_lines, $mapped_from_lines, $mapped_to_lines) {
		assert(sizeof($from_lines) == sizeof($mapped_from_lines));
		assert(sizeof($to_lines) == sizeof($mapped_to_lines));

		$this->Diff($mapped_from_lines, $mapped_to_lines);

		$xi = $yi = 0;
		for ($i = 0; $i < sizeof($this->edits); $i++) {
			$orig = &$this->edits[$i]->orig;
			if (is_array($orig)) {
				$orig = array_slice($from_lines, $xi, sizeof($orig));
				$xi += sizeof($orig);
			}

			$closing = &$this->edits[$i]->closing;
			if (is_array($closing)) {
				$closing = array_slice($to_lines, $yi, sizeof($closing));
				$yi += sizeof($closing);
			}
		}
	}
}

class DiffFormatter {
	var $show_header = TRUE;
	var $leading_context_lines = 0;
	var $trailing_context_lines = 0;

	function format($diff) {
		$xi = $yi = 1;
		$block = FALSE;
		$context = array();

		$nlead = $this->leading_context_lines;
		$ntrail = $this->trailing_context_lines;

		$this->_start_diff();

		foreach ($diff->edits as $edit) {
			if ($edit->type == 'copy') {
				if (is_array($block)) {
					if (sizeof($edit->orig) <= $nlead + $ntrail) { $block[] = $edit; }
					else {
						if ($ntrail) {
							$context = array_slice($edit->orig, 0, $ntrail);
							$block[] = new _DiffOp_Copy($context);
						}
						$this->_block($x0, $ntrail + $xi - $x0, $y0, $ntrail + $yi - $y0, $block);
						$block = FALSE;
					}
				}
				$context = $edit->orig;
			}
			else {
				if (! is_array($block)) {
					$context = array_slice($context, sizeof($context) - $nlead);
					$x0 = $xi - sizeof($context);
					$y0 = $yi - sizeof($context);
					$block = array();
					if ($context) { $block[] = new _DiffOp_Copy($context); }
				}
				$block[] = $edit;
			}

			if ($edit->orig) { $xi += sizeof($edit->orig); }
			if ($edit->closing) { $yi += sizeof($edit->closing); }
		}

		if (is_array($block)) {
			$this->_block($x0, $xi - $x0, $y0, $yi - $y0, $block);
		}
		$end = $this->_end_diff();
		return $end;
	}

	function _block($xbeg, $xlen, $ybeg, $ylen, &$edits) {
		$this->_start_block($this->_block_header($xbeg, $xlen, $ybeg, $ylen));
		foreach ($edits as $edit) {
			if ($edit->type == 'copy') { $this->_context($edit->orig); }
			elseif ($edit->type == 'add') { $this->_added($edit->closing); }
			elseif ($edit->type == 'delete') { $this->_deleted($edit->orig); }
			elseif ($edit->type == 'change') { $this->_changed($edit->orig, $edit->closing); }
			else { trigger_error('Unknown edit type', E_USER_ERROR); }
		}
		$this->_end_block();
	}

	function _start_diff() { ob_start(); }
	function _end_diff() { $val = ob_get_contents(); ob_end_clean(); return $val; }

	function _block_header($xbeg, $xlen, $ybeg, $ylen) {
		if ($xlen > 1) { $xbeg .= ",".($xbeg + $xlen - 1); }
		if ($ylen > 1) { $ybeg .= ",".($ybeg + $ylen - 1); }
		return $xbeg . ($xlen ? ($ylen ? 'c' : 'd') : 'a').$ybeg;
	}

	function _start_block($header) { if ($this->show_header) { echo $header; } }
	function _end_block() { }
	function _lines($lines, $prefix = ' ') { foreach ($lines as $line) { echo "$prefix $line\n"; } }
	function _context($lines) { $this->_lines($lines); }
	function _added($lines) { $this->_lines($lines, '>'); }
	function _deleted($lines) { $this->_lines($lines, '<'); }
	function _changed($orig, $closing) { $this->_deleted($orig); echo "---\n"; $this->_added($closing); }
}

define('NBSP', '&#160;');

class _HWLDF_WordAccumulator {
	function _HWLDF_WordAccumulator() {
		$this->_lines = array();
		$this->_line = '';
		$this->_group = '';
		$this->_tag = '';
	}

	function _flushGroup($new_tag) {
		if ($this->_group !== '') {
			if ($this->_tag == 'mark') {
				$this->_line .= '<span class="diffchange">'.htmlspecialchars($this->_group, ENT_QUOTES).'</span>';
			}
			else {
				$this->_line .= htmlspecialchars($this->_group, ENT_QUOTES);
			}
		}
		$this->_group = '';
		$this->_tag = $new_tag;
	}

	function _flushLine($new_tag) {
		$this->_flushGroup($new_tag);
		if ($this->_line != '') {
			array_push($this->_lines, $this->_line);
		}
		else {
			array_push($this->_lines, NBSP);
		}
		$this->_line = '';
	}

	function addWords($words, $tag = '') {
		if ($tag != $this->_tag) {
			$this->_flushGroup($tag);
		}
		foreach ($words as $word) {
			if ($word == '') {
				continue;
			}
			if ($word[0] == "\n") {
				$this->_flushLine($tag);
				$word = substr($word, 1);
			}
			assert(!strstr($word, "\n"));
			$this->_group .= $word;
		}
	}

	function getLines() { $this->_flushLine('~done'); return $this->_lines; }
}

class WordLevelDiff extends MappedDiff {
	function MAX_LINE_LENGTH() { return 10000; }

	function WordLevelDiff($orig_lines, $closing_lines) {
		list($orig_words, $orig_stripped) = $this->_split($orig_lines);
		list($closing_words, $closing_stripped) = $this->_split($closing_lines);

		$this->MappedDiff($orig_words, $closing_words, $orig_stripped, $closing_stripped);
	}

	function _split($lines) {
		$words = array();
		$stripped = array();
		$first = TRUE;
		foreach ($lines as $line) {
			if ($first) {
				$first = FALSE;
			}
			else {
				$words[] = "\n";
				$stripped[] = "\n";
			}
			if (strlen($line) > $this->MAX_LINE_LENGTH()) {
				$words[] = $line;
				$stripped[] = $line;
			}
			else {
				if (preg_match_all('/ ( [^\S\n]+ | [0-9_A-Za-z\x80-\xff]+ | . ) (?: (?!< \n) [^\S\n])? /xs', $line, $m)) {
					$words = array_merge($words, $m[0]);
					$stripped = array_merge($stripped, $m[1]);
				}
			}
		}
		return array($words, $stripped);
	}

	function orig() {
		$orig = new _HWLDF_WordAccumulator;

		foreach ($this->edits as $edit) {
			if ($edit->type == 'copy') {
				$orig->addWords($edit->orig);
			}
			elseif ($edit->orig) {
				$orig->addWords($edit->orig, 'mark');
			}
		}
		$lines = $orig->getLines();
		return $lines;
	}

	function closing() {
		$closing = new _HWLDF_WordAccumulator;

		foreach ($this->edits as $edit) {
			if ($edit->type == 'copy') {
				$closing->addWords($edit->closing);
			}
			elseif ($edit->closing) {
				$closing->addWords($edit->closing, 'mark');
			}
		}
		$lines = $closing->getLines();
		return $lines;
	}
}

class WikeasyDiffFormatter extends DiffFormatter {
	var $rows;

	public function __construct() {
		$this->leading_context_lines = 2;
		$this->trailing_context_lines = 2;
	}

	function _start_diff() { $this->rows = array(); }
	function _end_diff() {	return $this->rows; }

	function _block_header($xbeg, $xlen, $ybeg, $ylen) {
		return array(
			array('data' => '<strong>Ligne '.$xbeg.'</strong>', 'colspan' => 2),
			array('data' => '<strong>Ligne '.$ybeg.'</strong>', 'colspan' => 2)
		);
	}

	function _start_block($header) { if ($this->show_header) { $this->rows[] = $header; } }
	function _end_block() { }
	function _lines($lines, $prefix=' ', $color='white') { }
	function addedLine($line) { return array(array('data' => '+'), array('data' => '<div>'.$line.'</div>', 'class' => 'diff-addedline')); }
	function deletedLine($line) { return array(array('data' => '-'), array('data' => '<div>'.$line.'</div>', 'class' => 'diff-deletedline')); }
	function contextLine($line) { return array(array('data' => '&nbsp;'), array('data' => '<div>'.$line.'</div>', 'class' => 'diff-context')); }
	function emptyLine() { return array(array('data' =>'&nbsp;'), array('data' => '&nbsp;')); }

	function _added($lines) {
		foreach ($lines as $line) {
			$this->rows[] = array_merge($this->emptyLine(), $this->addedLine(htmlspecialchars($line, ENT_QUOTES)));
		}
	}

	function _deleted($lines) {
		foreach ($lines as $line) {
			$this->rows[] = array_merge($this->deletedLine(htmlspecialchars($line, ENT_QUOTES)), $this->emptyLine());
		}
	}

	function _context($lines) {
		foreach ($lines as $line) {
			$this->rows[] = array_merge($this->contextLine(htmlspecialchars($line, ENT_QUOTES)), $this->contextLine(htmlspecialchars($line, ENT_QUOTES)));
		}
	}

	function _changed($orig, $closing) {
		$diff = new WordLevelDiff($orig, $closing);
		$del = $diff->orig();
		$add = $diff->closing();
		while ($line = array_shift($del)) {
			$aline = array_shift($add);
			$this->rows[] = array_merge($this->deletedLine($line), $this->addedLine($aline));
		}
		foreach ($add as $line) { $this->rows[] = array_merge($this->emptyLine(), $this->addedLine($line)); }
	}
}

/* End of file diffengine.php */