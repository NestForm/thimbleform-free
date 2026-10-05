<?php
/**
 * Minimal XLSX writer for exporting tabular data.
 *
 * Generates a valid .xlsx file without external dependencies.
 *
 * @package Thimbleform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Thimbleform_Xlsx_Export {

	/**
	 * Create a single-sheet XLSX file and send it to the browser.
	 *
	 * @param string   $filename Output filename without extension.
	 * @param array    $headers  Column headers.
	 * @param iterable $rows     Row arrays; a Generator is fine.
	 */
	public static function download( $filename, array $headers, $rows ) {
		self::download_sheets(
			$filename,
			array(
				array(
					'name'    => __( 'Submissions', 'thimbleform' ),
					'headers' => $headers,
					'rows'    => $rows,
				),
			)
		);
	}

	/**
	 * Create a workbook of one or more sheets and send it to the browser.
	 *
	 * @param string $filename Output filename without extension.
	 * @param array  $sheets   Sheets, each `['name' => string, 'headers' => array, 'rows' => iterable]`.
	 */
	public static function download_sheets( $filename, array $sheets ) {
		$filename = sanitize_file_name( $filename ) . '.xlsx';
		$xlsx     = self::generate_sheets( $sheets );

		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: max-age=0' );
		header( 'Content-Length: ' . strlen( $xlsx ) );

		$download = fopen( 'php://output', 'wb' );
		if ( false !== $download ) {
			fwrite( $download, $xlsx );
			fclose( $download );
		}
		exit;
	}

	/**
	 * Generate a single-sheet XLSX binary string.
	 *
	 * @param array    $headers Column headers.
	 * @param iterable $rows    Row data; a Generator is fine.
	 * @return string XLSX contents.
	 */
	public static function generate( array $headers, $rows ) {
		return self::generate_sheets(
			array(
				array(
					'name'    => __( 'Submissions', 'thimbleform' ),
					'headers' => $headers,
					'rows'    => $rows,
				),
			)
		);
	}

	/**
	 * Generate a multi-sheet XLSX binary string.
	 *
	 * @param array $sheets Sheets, each `['name' => string, 'headers' => array, 'rows' => iterable]`.
	 *                      A sheet may omit `headers` to start straight at its rows.
	 * @return string XLSX contents.
	 */
	public static function generate_sheets( array $sheets ) {
		$sheets = array_values( array_filter( $sheets, 'is_array' ) );

		if ( empty( $sheets ) ) {
			$sheets = array(
				array(
					'name'    => __( 'Submissions', 'thimbleform' ),
					'headers' => array(),
					'rows'    => array(),
				),
			);
		}

		$names = self::unique_sheet_names( wp_list_pluck( $sheets, 'name' ) );

		$zip_file = wp_tempnam();
		$zip      = new ZipArchive();
		if ( $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			throw new Exception( esc_html__( 'Unable to create XLSX archive.', 'thimbleform' ) );
		}

		$count = count( $sheets );

		$zip->addFromString( '[Content_Types].xml', self::content_types_xml( $count ) );
		$zip->addFromString( '_rels/.rels', self::root_rels_xml() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml( $names ) );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels_xml( $count ) );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );

		foreach ( $sheets as $index => $sheet ) {
			$zip->addFromString(
				'xl/worksheets/sheet' . ( $index + 1 ) . '.xml',
				self::sheet_xml( (array) ( isset( $sheet['headers'] ) ? $sheet['headers'] : array() ), isset( $sheet['rows'] ) ? $sheet['rows'] : array() )
			);
		}

		$zip->close();

		$xlsx = file_get_contents( $zip_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local temp file this method just wrote, never a URL.

		wp_delete_file( $zip_file );

		return $xlsx;
	}

	/**
	 * One worksheet's XML.
	 *
	 * @param array    $headers Column headers; an empty array writes no header row.
	 * @param iterable $rows    Row data.
	 * @return string
	 */
	private static function sheet_xml( array $headers, $rows ) {
		$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$xml .= '<sheetData>';

		$row_index = 0;

		if ( ! empty( $headers ) ) {
			++$row_index;
			$xml .= self::build_row( $row_index, $headers, true );
		}

		foreach ( $rows as $row ) {
			++$row_index;
			$xml .= self::build_row( $row_index, (array) $row, false );
		}

		$xml .= '</sheetData></worksheet>';

		return $xml;
	}

	/**
	 * Makes a set of sheet names Excel will actually open.
	 *
	 * @param array $names Requested names, in sheet order.
	 * @return string[] Safe, unique names.
	 */
	private static function unique_sheet_names( array $names ) {
		$safe = array();
		$used = array();

		foreach ( array_values( $names ) as $index => $name ) {
			$name = str_replace( array( '[', ']', ':', '*', '?', '/', '\\' ), '', (string) $name );
			$name = trim( mb_substr( $name, 0, 31 ) );

			if ( '' === $name ) {
				/* translators: %d: sheet number. */
				$name = sprintf( __( 'Sheet %d', 'thimbleform' ), $index + 1 );
			}

			$candidate = $name;
			$suffix    = 2;
			while ( isset( $used[ strtolower( $candidate ) ] ) ) {
				$tail      = ' (' . $suffix . ')';
				$candidate = mb_substr( $name, 0, 31 - mb_strlen( $tail ) ) . $tail;
				++$suffix;
			}

			$used[ strtolower( $candidate ) ] = true;
			$safe[]                           = $candidate;
		}

		return $safe;
	}

	/**
	 * @param int   $row_index Row number (1-based).
	 * @param array $cells     Cell values.
	 * @param bool  $is_header Whether this is the header row.
	 * @return string
	 */
	private static function build_row( $row_index, array $cells, $is_header ) {
		$xml = '<row r="' . (int) $row_index . '">';
		$col = 0;
		foreach ( $cells as $cell ) {
			++$col;
			$ref   = self::column_letter( $col ) . (int) $row_index;
			$style = $is_header ? ' s="1"' : '';

			if ( is_array( $cell ) ) {
				$cell = implode( ', ', $cell );
			}

			$is_safe_numeric = false;
			if ( is_numeric( $cell ) ) {
				$str_value = (string) $cell;
				$len       = strlen( $str_value );

				if ( $len <= 15 && 0 !== strpos( $str_value, '+' ) ) {
					if ( 0 === strpos( $str_value, '0' ) ) {
						$is_safe_numeric = ( '0' === $str_value || 0 === strpos( $str_value, '0.' ) );
					} else {
						$is_safe_numeric = true;
					}
				}
			}

			if ( $is_safe_numeric ) {
				$xml .= '<c r="' . $ref . '"' . $style . ' t="n"><v>' . (float) $cell . '</v></c>';
			} else {
				$str_value = self::neutralize_formula( (string) $cell );

				$xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t>' . self::escape_xml( $str_value ) . '</t></is></c>';
			}
		}
		$xml .= '</row>';
		return $xml;
	}

	/**
	 * Prefix formula-like cell values so spreadsheets treat them as text.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function neutralize_formula( $value ) {
		if ( '' === $value ) {
			return $value;
		}

		$first = $value[0];

		if ( in_array( $first, array( '=', '+', '-', '@' ), true ) || "\t" === $first ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * @param int $col 1-based column index.
	 * @return string
	 */
	private static function column_letter( $col ) {
		$letter = '';
		$col    = (int) $col;
		while ( $col > 0 ) {
			$remainder = ( $col - 1 ) % 26;
			$letter    = chr( 65 + $remainder ) . $letter;
			$col       = (int) floor( ( $col - 1 ) / 26 );
		}
		return $letter;
	}

	/**
	 * @param string $value Raw text.
	 * @return string
	 */
	private static function escape_xml( $value ) {
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		return str_replace( array( '&', '<', '>', '"', "'" ), array( '&amp;', '&lt;', '&gt;', '&quot;', '&apos;' ), $value );
	}

	/**
	 * @param int $sheet_count Number of worksheets.
	 * @return string
	 */
	private static function content_types_xml( $sheet_count = 1 ) {
		$overrides = '';
		for ( $i = 1; $i <= (int) $sheet_count; $i++ ) {
			$overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
			'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
			'<Default Extension="xml" ContentType="application/xml"/>' .
			'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
			$overrides .
			'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
			'</Types>';
	}

	/**
	 * @return string
	 */
	private static function root_rels_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
			'</Relationships>';
	}

	/**
	 * @param int $sheet_count Number of worksheets.
	 * @return string
	 */
	private static function workbook_rels_xml( $sheet_count = 1 ) {
		$rels = '';
		for ( $i = 1; $i <= (int) $sheet_count; $i++ ) {
			$rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
		}

		$rels .= '<Relationship Id="rId' . ( (int) $sheet_count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
			$rels .
			'</Relationships>';
	}

	/**
	 * @param string[] $names Sheet names, already made safe and unique.
	 * @return string
	 */
	private static function workbook_xml( array $names ) {
		$sheets = '';
		foreach ( array_values( $names ) as $index => $name ) {
			$sheets .= '<sheet name="' . self::escape_xml( $name ) . '" sheetId="' . ( $index + 1 ) . '" r:id="rId' . ( $index + 1 ) . '"/>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
			'<sheets>' . $sheets . '</sheets>' .
			'</workbook>';
	}

	/**
	 * @return string
	 */
	private static function styles_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
			'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
			'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
			'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
			'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
			'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
			'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
			'</styleSheet>';
	}
}
