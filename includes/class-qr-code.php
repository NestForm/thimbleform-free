<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offline SVG QR encoder (byte mode, ECC M, versions 1-10).
 *
 * @package Thimbleform
 */
class Nestform_Qr_Code {

	/**
	 * Error-correction level M: the 2-bit indicator that goes into the format
	 * information. M corrects ~15% damage, the usual choice for a printed or
	 * on-screen link.
	 */
	private const EC_LEVEL_BITS = 0b00;

	/**
	 * Block structure per version, for error-correction level M.
	 *
	 * [ EC codewords per block, [ [ block count, data codewords per block ], ... ] ]
	 *
	 * @var array<int, array{0: int, 1: array<int, array{0: int, 1: int}>}>
	 */
	private const EC_BLOCKS = [
		1  => [ 10, [ [ 1, 16 ] ] ],
		2  => [ 16, [ [ 1, 28 ] ] ],
		3  => [ 26, [ [ 1, 44 ] ] ],
		4  => [ 18, [ [ 2, 32 ] ] ],
		5  => [ 24, [ [ 2, 43 ] ] ],
		6  => [ 16, [ [ 4, 27 ] ] ],
		7  => [ 18, [ [ 4, 31 ] ] ],
		8  => [ 22, [ [ 2, 38 ], [ 2, 39 ] ] ],
		9  => [ 22, [ [ 3, 36 ], [ 2, 37 ] ] ],
		10 => [ 26, [ [ 4, 43 ], [ 1, 44 ] ] ],
	];

	/**
	 * Row/column centres of the alignment patterns, per version. Version 1 has
	 * none.
	 *
	 * @var array<int, array<int, int>>
	 */
	private const ALIGNMENT_CENTERS = [
		1  => [],
		2  => [ 6, 18 ],
		3  => [ 6, 22 ],
		4  => [ 6, 26 ],
		5  => [ 6, 30 ],
		6  => [ 6, 34 ],
		7  => [ 6, 22, 38 ],
		8  => [ 6, 24, 42 ],
		9  => [ 6, 26, 46 ],
		10 => [ 6, 28, 50 ],
	];

	private const MAX_VERSION = 10;

	/**
	 * Render $text as an SVG QR code, or '' if it does not fit.
	 *
	 * The SVG carries no width/height so it scales to whatever box the CSS
	 * gives it; `shape-rendering="crispEdges"` keeps the modules from being
	 * blurred by antialiasing at small sizes.
	 *
	 * @param string $text  The data to encode - a URL, in practice.
	 * @param string $label Accessible name for the image. Never pass the URL
	 *                      itself: a screen reader announcing a full link is
	 *                      noise, and it would be echoed into the page.
	 */
	public static function svg( string $text, string $label = '' ): string {
		$matrix = self::matrix( $text );

		if ( empty( $matrix ) ) {
			return '';
		}

		$quiet = 4;
		$size  = count( $matrix );
		$total = $size + ( $quiet * 2 );

		// One path for every dark module beats one <rect> each: same pixels,
		// a fraction of the markup for a code that can run to 3,000 modules.
		$path = '';
		foreach ( $matrix as $row => $cells ) {
			foreach ( $cells as $col => $dark ) {
				if ( $dark ) {
					$path .= sprintf( 'M%d %dh1v1h-1z', $col + $quiet, $row + $quiet );
				}
			}
		}

		if ( '' === $label ) {
			$label = __( 'QR code for this form', 'nestform' );
		}

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s" shape-rendering="crispEdges">'
				. '<rect width="%1$d" height="%1$d" fill="#ffffff"/>'
				. '<path fill="#000000" d="%3$s"/>'
				. '</svg>',
			$total,
			esc_attr( $label ),
			$path
		);
	}

	/**
	 * Encode $text into a module matrix.
	 *
	 * @return array<int, array<int, bool>> Row-major, true meaning dark. Empty
	 *                                      if the text is empty or too long.
	 */
	public static function matrix( string $text ): array {
		if ( '' === $text ) {
			return [];
		}

		$version = self::smallest_version( strlen( $text ) );

		if ( 0 === $version ) {
			return [];
		}

		$codewords = self::codewords( $text, $version );
		$size      = ( $version * 4 ) + 17;

		[ $matrix, $reserved ] = self::function_patterns( $version, $size );

		self::place_data( $matrix, $reserved, $codewords, $size );

		return self::best_masked( $matrix, $reserved, $version, $size );
	}

	/**
	 * The smallest version whose byte-mode capacity holds $length bytes, or 0
	 * if none does.
	 */
	private static function smallest_version( int $length ): int {
		for ( $version = 1; $version <= self::MAX_VERSION; $version++ ) {
			if ( $length <= self::byte_capacity( $version ) ) {
				return $version;
			}
		}

		return 0;
	}

	/**
	 * Bytes encodable at $version, after the 4-bit mode indicator and the
	 * character-count indicator have taken their share.
	 */
	private static function byte_capacity( int $version ): int {
		$header_bits = 4 + self::count_bits( $version );

		return (int) floor( ( ( self::data_codewords( $version ) * 8 ) - $header_bits ) / 8 );
	}

	/**
	 * Width of the byte-mode character-count indicator: 8 bits up to version 9,
	 * 16 from version 10.
	 */
	private static function count_bits( int $version ): int {
		return $version < 10 ? 8 : 16;
	}

	private static function data_codewords( int $version ): int {
		[ , $groups ] = self::EC_BLOCKS[ $version ];

		$total = 0;
		foreach ( $groups as [ $count, $data ] ) {
			$total += $count * $data;
		}

		return $total;
	}

	/**
	 * Build the final codeword bit stream: encoded data, padded, split into
	 * blocks, error-corrected, and interleaved as the standard requires.
	 *
	 * @return array<int, int> One bit (0 or 1) per element.
	 */
	private static function codewords( string $text, int $version ): array {
		$data = self::encode_data( $text, $version );

		[ $ec_length, $groups ] = self::EC_BLOCKS[ $version ];

		// Split into blocks, and error-correct each one separately.
		$data_blocks = [];
		$ec_blocks   = [];
		$offset      = 0;

		foreach ( $groups as [ $count, $block_size ] ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$block         = array_slice( $data, $offset, $block_size );
				$offset       += $block_size;
				$data_blocks[] = $block;
				$ec_blocks[]   = self::error_correction( $block, $ec_length );
			}
		}

		// Interleave: one codeword from each block in turn, data first, then
		// EC. Blocks are not all the same length, so short ones drop out.
		$bytes = [];

		$longest = max( array_map( 'count', $data_blocks ) );
		for ( $i = 0; $i < $longest; $i++ ) {
			foreach ( $data_blocks as $block ) {
				if ( isset( $block[ $i ] ) ) {
					$bytes[] = $block[ $i ];
				}
			}
		}

		for ( $i = 0; $i < $ec_length; $i++ ) {
			foreach ( $ec_blocks as $block ) {
				$bytes[] = $block[ $i ];
			}
		}

		$bits = [];
		foreach ( $bytes as $byte ) {
			for ( $i = 7; $i >= 0; $i-- ) {
				$bits[] = ( $byte >> $i ) & 1;
			}
		}

		return $bits;
	}

	/**
	 * Mode indicator, character count, the bytes themselves, then terminator
	 * and padding out to the version's data capacity.
	 *
	 * @return array<int, int> Data codewords.
	 */
	private static function encode_data( string $text, int $version ): array {
		$capacity_bits = self::data_codewords( $version ) * 8;

		$bits = [];

		// Byte mode.
		self::push_bits( $bits, 0b0100, 4 );
		self::push_bits( $bits, strlen( $text ), self::count_bits( $version ) );

		foreach ( unpack( 'C*', $text ) as $byte ) {
			self::push_bits( $bits, $byte, 8 );
		}

		// Terminator: up to four zero bits, fewer if the stream is nearly full.
		$terminator = min( 4, $capacity_bits - count( $bits ) );
		self::push_bits( $bits, 0, $terminator );

		// Pad to a whole codeword.
		if ( count( $bits ) % 8 !== 0 ) {
			self::push_bits( $bits, 0, 8 - ( count( $bits ) % 8 ) );
		}

		$codewords = [];
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$byte = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$byte = ( $byte << 1 ) | $bits[ $i + $j ];
			}
			$codewords[] = $byte;
		}

		// Fill the remainder with the two alternating pad codewords the
		// standard specifies.
		$pad = [ 0xEC, 0x11 ];
		$i   = 0;
		while ( count( $codewords ) < self::data_codewords( $version ) ) {
			$codewords[] = $pad[ $i % 2 ];
			$i++;
		}

		return $codewords;
	}

	/**
	 * @param array<int, int> $bits Appended to in place.
	 */
	private static function push_bits( array &$bits, int $value, int $length ): void {
		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$bits[] = ( $value >> $i ) & 1;
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Reed-Solomon over GF(256), primitive polynomial 0x11D.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * Exponent and logarithm tables for GF(256), built once per request.
	 *
	 * @return array{0: array<int, int>, 1: array<int, int>}
	 */
	private static function gf_tables(): array {
		static $exp = null;
		static $log = null;

		if ( null === $exp ) {
			$exp = array_fill( 0, 512, 0 );
			$log = array_fill( 0, 256, 0 );

			$x = 1;
			for ( $i = 0; $i < 255; $i++ ) {
				$exp[ $i ] = $x;
				$log[ $x ] = $i;

				$x <<= 1;
				if ( $x & 0x100 ) {
					$x ^= 0x11D;
				}
			}

			// Doubling the exponent table lets gf_multiply() add logarithms
			// without a modulo.
			for ( $i = 255; $i < 512; $i++ ) {
				$exp[ $i ] = $exp[ $i - 255 ];
			}
		}

		return [ $exp, $log ];
	}

	private static function gf_multiply( int $a, int $b ): int {
		if ( 0 === $a || 0 === $b ) {
			return 0;
		}

		[ $exp, $log ] = self::gf_tables();

		return $exp[ $log[ $a ] + $log[ $b ] ];
	}

	/**
	 * The generator polynomial of the given degree: (x - a^0)(x - a^1)...
	 *
	 * @return array<int, int> Coefficients, highest power first.
	 */
	private static function generator_polynomial( int $degree ): array {
		[ $exp ] = self::gf_tables();

		$poly = [ 1 ];

		for ( $i = 0; $i < $degree; $i++ ) {
			$next = array_fill( 0, count( $poly ) + 1, 0 );

			foreach ( $poly as $power => $coefficient ) {
				$next[ $power ]       ^= $coefficient;
				$next[ $power + 1 ]   ^= self::gf_multiply( $coefficient, $exp[ $i ] );
			}

			$poly = $next;
		}

		return $poly;
	}

	/**
	 * The EC codewords for one block: the remainder of the data polynomial
	 * divided by the generator polynomial.
	 *
	 * @param array<int, int> $block Data codewords.
	 * @return array<int, int> $length EC codewords.
	 */
	private static function error_correction( array $block, int $length ): array {
		$generator = self::generator_polynomial( $length );
		$remainder = array_fill( 0, $length, 0 );

		foreach ( $block as $codeword ) {
			$factor = $codeword ^ $remainder[0];

			array_shift( $remainder );
			$remainder[] = 0;

			if ( 0 === $factor ) {
				continue;
			}

			// $generator[0] is always 1 and lines up with the codeword just
			// shifted out, so the loop starts at 1.
			for ( $i = 1; $i <= $length; $i++ ) {
				$remainder[ $i - 1 ] ^= self::gf_multiply( $generator[ $i ], $factor );
			}
		}

		return $remainder;
	}

	/*
	 * ---------------------------------------------------------------------
	 * Matrix construction.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * The patterns a decoder needs in order to find and orient the code:
	 * finders, separators, timing, alignment and the lone dark module.
	 *
	 * Returns the matrix alongside a parallel map of which cells are spoken
	 * for - data placement and masking must both leave those alone.
	 *
	 * @return array{0: array<int, array<int, bool>>, 1: array<int, array<int, bool>>}
	 */
	private static function function_patterns( int $version, int $size ): array {
		$matrix   = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

		// Finder patterns, plus the one-module separator around each. The loop
		// runs from -1 to 7 so the separator is drawn (as light modules) in the
		// same pass; out-of-bounds cells are simply skipped.
		foreach ( [ [ 0, 0 ], [ 0, $size - 7 ], [ $size - 7, 0 ] ] as [ $top, $left ] ) {
			for ( $i = -1; $i <= 7; $i++ ) {
				for ( $j = -1; $j <= 7; $j++ ) {
					$row = $top + $i;
					$col = $left + $j;

					if ( $row < 0 || $row >= $size || $col < 0 || $col >= $size ) {
						continue;
					}

					$outer_ring = ( $i >= 0 && $i <= 6 && ( 0 === $j || 6 === $j ) )
						|| ( $j >= 0 && $j <= 6 && ( 0 === $i || 6 === $i ) );
					$center     = $i >= 2 && $i <= 4 && $j >= 2 && $j <= 4;

					$matrix[ $row ][ $col ]   = $outer_ring || $center;
					$reserved[ $row ][ $col ] = true;
				}
			}
		}

		// Timing patterns: alternating modules bridging the finders.
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$dark = 0 === $i % 2;

			$matrix[6][ $i ]   = $dark;
			$reserved[6][ $i ] = true;
			$matrix[ $i ][6]   = $dark;
			$reserved[ $i ][6] = true;
		}

		// Alignment patterns, at every combination of centres except the three
		// that would collide with a finder.
		$centers = self::ALIGNMENT_CENTERS[ $version ];
		$last    = $size - 7;

		foreach ( $centers as $row_center ) {
			foreach ( $centers as $col_center ) {
				$collides = ( 6 === $row_center && 6 === $col_center )
					|| ( 6 === $row_center && $last === $col_center )
					|| ( $last === $row_center && 6 === $col_center );

				if ( $collides ) {
					continue;
				}

				for ( $i = -2; $i <= 2; $i++ ) {
					for ( $j = -2; $j <= 2; $j++ ) {
						$matrix[ $row_center + $i ][ $col_center + $j ]   = 1 !== max( abs( $i ), abs( $j ) );
						$reserved[ $row_center + $i ][ $col_center + $j ] = true;
					}
				}
			}
		}

		// The dark module, always at this position, and always dark.
		$matrix[ $size - 8 ][8]   = true;
		$reserved[ $size - 8 ][8] = true;

		// Reserve (but do not yet fill) the format information, which cannot
		// be computed until the mask is chosen.
		for ( $i = 0; $i <= 8; $i++ ) {
			$reserved[8][ $i ] = true;
			$reserved[ $i ][8] = true;
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$reserved[8][ $size - 1 - $i ] = true;
			$reserved[ $size - 1 - $i ][8] = true;
		}

		// Version information, from version 7 up: two 6x3 blocks.
		if ( $version >= 7 ) {
			for ( $i = 0; $i < 18; $i++ ) {
				$row = intdiv( $i, 3 );
				$col = ( $i % 3 ) + $size - 11;

				$reserved[ $row ][ $col ] = true;
				$reserved[ $col ][ $row ] = true;
			}
		}

		return [ $matrix, $reserved ];
	}

	/**
	 * Lay the bit stream into the matrix: two modules wide, bottom to top and
	 * back again, skipping the vertical timing column and every reserved cell.
	 *
	 * @param array<int, array<int, bool>> $matrix   Written to in place.
	 * @param array<int, array<int, bool>> $reserved Read only.
	 * @param array<int, int>              $bits
	 */
	private static function place_data( array &$matrix, array $reserved, array $bits, int $size ): void {
		$index     = 0;
		$row       = $size - 1;
		$direction = -1;
		$total     = count( $bits );

		for ( $col = $size - 1; $col > 0; $col -= 2 ) {
			// Column 6 is the timing pattern; the two-wide strips step over it.
			if ( 6 === $col ) {
				--$col;
			}

			while ( true ) {
				for ( $offset = 0; $offset < 2; $offset++ ) {
					$current = $col - $offset;

					if ( $reserved[ $row ][ $current ] ) {
						continue;
					}

					// Codewords rarely fill the matrix exactly; the leftover
					// modules stay light.
					$matrix[ $row ][ $current ] = $index < $total && 1 === $bits[ $index ];
					++$index;
				}

				$row += $direction;

				if ( $row < 0 || $row >= $size ) {
					$row      -= $direction;
					$direction = -$direction;
					break;
				}
			}
		}
	}

	/**
	 * Whether module ($row, $col) is inverted by mask pattern $mask.
	 */
	private static function mask_applies( int $mask, int $row, int $col ): bool {
		switch ( $mask ) {
			case 0:
				return 0 === ( $row + $col ) % 2;
			case 1:
				return 0 === $row % 2;
			case 2:
				return 0 === $col % 3;
			case 3:
				return 0 === ( $row + $col ) % 3;
			case 4:
				return 0 === ( intdiv( $row, 2 ) + intdiv( $col, 3 ) ) % 2;
			case 5:
				return 0 === ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 );
			case 6:
				return 0 === ( ( ( $row * $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2;
			default:
				return 0 === ( ( ( $row + $col ) % 2 ) + ( ( $row * $col ) % 3 ) ) % 2;
		}
	}

	/**
	 * @param array<int, array<int, bool>> $matrix Written to in place.
	 * @param array<int, array<int, bool>> $reserved
	 */
	private static function apply_mask( array &$matrix, array $reserved, int $mask, int $size ): void {
		for ( $row = 0; $row < $size; $row++ ) {
			for ( $col = 0; $col < $size; $col++ ) {
				if ( $reserved[ $row ][ $col ] ) {
					continue;
				}

				if ( self::mask_applies( $mask, $row, $col ) ) {
					$matrix[ $row ][ $col ] = ! $matrix[ $row ][ $col ];
				}
			}
		}
	}

	/**
	 * Finish each of the eight masked candidates and return the one a scanner
	 * will find easiest, as scored by the standard's four penalty rules.
	 *
	 * Every candidate is completed - mask, format information and, from
	 * version 7, version information - before it is scored. Scoring a
	 * half-finished symbol would weigh modules that are not what the decoder
	 * ends up seeing, and can pick the wrong mask.
	 *
	 * @param array<int, array<int, bool>> $matrix
	 * @param array<int, array<int, bool>> $reserved
	 * @return array<int, array<int, bool>>
	 */
	private static function best_masked( array $matrix, array $reserved, int $version, int $size ): array {
		$best  = [];
		$score = PHP_INT_MAX;

		for ( $mask = 0; $mask < 8; $mask++ ) {
			$candidate = $matrix;

			self::apply_mask( $candidate, $reserved, $mask, $size );
			self::place_format_info( $candidate, $mask, $size );

			if ( $version >= 7 ) {
				self::place_version_info( $candidate, $version, $size );
			}

			$penalty = self::penalty( $candidate, $size );

			if ( $penalty < $score ) {
				$score = $penalty;
				$best  = $candidate;
			}
		}

		return $best;
	}

	/**
	 * The four penalty rules, summed. Lower is better.
	 *
	 * @param array<int, array<int, bool>> $matrix
	 */
	private static function penalty( array $matrix, int $size ): int {
		$penalty = 0;

		// Rule 1: runs of five or more identical modules in a row or column.
		for ( $i = 0; $i < $size; $i++ ) {
			foreach ( [ true, false ] as $horizontal ) {
				$run_value  = null;
				$run_length = 0;

				for ( $j = 0; $j < $size; $j++ ) {
					$value = $horizontal ? $matrix[ $i ][ $j ] : $matrix[ $j ][ $i ];

					if ( $value === $run_value ) {
						++$run_length;
					} else {
						$run_value  = $value;
						$run_length = 1;
					}

					if ( 5 === $run_length ) {
						$penalty += 3;
					} elseif ( $run_length > 5 ) {
						++$penalty;
					}
				}
			}
		}

		// Rule 2: every 2x2 block of one colour.
		for ( $row = 0; $row < $size - 1; $row++ ) {
			for ( $col = 0; $col < $size - 1; $col++ ) {
				$value = $matrix[ $row ][ $col ];

				if ( $value === $matrix[ $row ][ $col + 1 ]
					&& $value === $matrix[ $row + 1 ][ $col ]
					&& $value === $matrix[ $row + 1 ][ $col + 1 ] ) {
					$penalty += 3;
				}
			}
		}

		// Rule 3: the finder-like 1:1:3:1:1 sequence, which a scanner could
		// mistake for a real finder pattern.
		$patterns = [
			[ true, false, true, true, true, false, true, false, false, false, false ],
			[ false, false, false, false, true, false, true, true, true, false, true ],
		];

		for ( $i = 0; $i < $size; $i++ ) {
			for ( $j = 0; $j <= $size - 11; $j++ ) {
				foreach ( $patterns as $pattern ) {
					$horizontal = true;
					$vertical   = true;

					for ( $k = 0; $k < 11; $k++ ) {
						if ( $matrix[ $i ][ $j + $k ] !== $pattern[ $k ] ) {
							$horizontal = false;
						}
						if ( $matrix[ $j + $k ][ $i ] !== $pattern[ $k ] ) {
							$vertical = false;
						}
					}

					if ( $horizontal ) {
						$penalty += 40;
					}
					if ( $vertical ) {
						$penalty += 40;
					}
				}
			}
		}

		// Rule 4: how far the proportion of dark modules strays from half.
		$dark = 0;
		foreach ( $matrix as $cells ) {
			foreach ( $cells as $value ) {
				if ( $value ) {
					++$dark;
				}
			}
		}

		$percent  = ( $dark * 100 ) / ( $size * $size );
		$penalty += (int) ( floor( abs( $percent - 50 ) / 5 ) * 10 );

		return $penalty;
	}

	/**
	 * Write the 15-bit format information - EC level and mask - into both of
	 * the places a decoder looks for it.
	 *
	 * @param array<int, array<int, bool>> $matrix Written to in place.
	 */
	private static function place_format_info( array &$matrix, int $mask, int $size ): void {
		$format = self::format_bits( ( self::EC_LEVEL_BITS << 3 ) | $mask );

		for ( $i = 0; $i < 15; $i++ ) {
			$dark = 1 === ( ( $format >> $i ) & 1 );

			// Copy one: down the left of the top-left finder, then up the
			// bottom-left one, stepping over the timing row.
			if ( $i < 6 ) {
				$matrix[ $i ][8] = $dark;
			} elseif ( $i < 8 ) {
				$matrix[ $i + 1 ][8] = $dark;
			} else {
				$matrix[ $size - 15 + $i ][8] = $dark;
			}

			// Copy two: right to left along row 8, and back from the top-right
			// finder, again stepping over the timing column.
			if ( $i < 8 ) {
				$matrix[8][ $size - 1 - $i ] = $dark;
			} elseif ( $i < 9 ) {
				$matrix[8][ 15 - $i ] = $dark;
			} else {
				$matrix[8][ 14 - $i ] = $dark;
			}
		}
	}

	/**
	 * Write the 18-bit version information (versions 7 and up).
	 *
	 * @param array<int, array<int, bool>> $matrix Written to in place.
	 */
	private static function place_version_info( array &$matrix, int $version, int $size ): void {
		$bits = self::version_bits( $version );

		for ( $i = 0; $i < 18; $i++ ) {
			$dark = 1 === ( ( $bits >> $i ) & 1 );

			$row = intdiv( $i, 3 );
			$col = ( $i % 3 ) + $size - 11;

			$matrix[ $row ][ $col ] = $dark;
			$matrix[ $col ][ $row ] = $dark;
		}
	}

	/**
	 * BCH(15,5) error correction over the 5 format bits, then the standard's
	 * fixed XOR mask - which stops an all-zero format from reading as valid.
	 */
	private static function format_bits( int $data ): int {
		$remainder = $data << 10;

		while ( self::bit_length( $remainder ) >= 11 ) {
			$remainder ^= 0x537 << ( self::bit_length( $remainder ) - 11 );
		}

		return ( ( $data << 10 ) | $remainder ) ^ 0x5412;
	}

	/**
	 * BCH(18,6) over the 6 version bits. No XOR mask here - version 7 and up
	 * are never all-zero.
	 */
	private static function version_bits( int $version ): int {
		$remainder = $version << 12;

		while ( self::bit_length( $remainder ) >= 13 ) {
			$remainder ^= 0x1F25 << ( self::bit_length( $remainder ) - 13 );
		}

		return ( $version << 12 ) | $remainder;
	}

	private static function bit_length( int $value ): int {
		$length = 0;

		while ( $value > 0 ) {
			++$length;
			$value >>= 1;
		}

		return $length;
	}
}
