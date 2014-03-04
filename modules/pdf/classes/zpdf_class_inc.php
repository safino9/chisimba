<?php

require_once("modules/pdf/resources/Pdf.php");

class zpdf extends object
{
	static $size = "SIZE_A4";


	public $pdf;
	private $style;

	public function init()
	{

	}

	/**
	 * Load methods
	 */

	/**
	 * Method to load a PDF file for use/edit
	 *
	 * @param file $file
	 * @param integer $revision
	 */
	public function loadPdf($file, $revision=NULL)
	{
		$this->pdf = Zend_Pdf::load($file, $revision);
	}

	/**
	 * Create a new PDF
	 *
	 * @param void
	 * @return void
	 */
	public function newPdf()
	{
		$this->pdf = new Zend_Pdf();
	}

	/**
	 * Load and parse a PDF string
	 *
	 * @param string $string
	 */
	public function loadPdfString($string)
	{
		$this->pdf = Zend_Pdf::parse($pdfString);
	}

	/**
	 * Load a specific revision of a PDF file
	 * This method will roll back to the specified revision in history
	 * for example $revisionNo = 1 will rollback one revision NOT rollback to revision 1
	 *
	 * @param file $file
	 * @param integer $revisionNo
	 */
	public function loadRevision($file, $revisionNo)
	{
		$this->pdf = Zend_Pdf::load($file);
		$revisions = $this->pdf->revisions();
		$this->pdf->rollback($revisions - $revisionNo);
	}

	/**
	 * Save methods
	 *
	 */

	/**
	 * Method to save a PDF as a file
	 *
	 * @param mixed $fileName
	 * @param boolean $update
	 * @param mixed $newFileName
	 */
	public function savePdf($fileName, $update=TRUE, $newFileName = '')
	{
		if($update == TRUE)
		{
			// Update document
			$this->pdf->save($fileName, true);
		}
		else {
			// Save document as a new file
			$this->pdf->save($newFileName);
		}
	}

	/**
	 * Method to return the PDF as a string
	 *
	 * @return string $pdfString
	 */
	public function pdfToString()
	{
		$pdfString = $this->pdf->render();
		return $pdfString;
	}

	/**
	 * Document Page methods
	 *
	 * newPage() method and the Page constructor take the same set of parameters.
	 * It either the size of page ($x, $y) in a points (1/72 inch), or predefined constant, which is treated as a page type:
	 *
	 * Page::SIZE_A4
	 * Page::SIZE_A4_LANDSCAPE
	 * Page::SIZE_LETTER
	 * Page::SIZE_LETTER_LANDSCAPE
	 *
	 * Document pages are stored in $pages public member of the Zend_Pdf class.
	 * It's an array of Zend_Pdf_Page objects.
	 * It completely defines set and order of document pages and can be manipulated as a common array:
	 */

	/**
	 * Page setup method. This MUST be called BEFORE adding your page set, otherwise page order will be reversed.
	 *
	 * @param void
	 * @return void
	 */
	public function setupPages()
	{
		//$this->size = $size;
		// Reverse page order
		$this->pdf->pages = array_reverse($this->pdf->pages);
		//create the initial page
		$this->pdf->pages[] = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_A4); // $this->size);
	}

	/**
	 * Method to add a new page to the PDF
	 * When using the edit option, new pages are appended onto the end of the pdf and the revision altered
	 *
	 * @param const $size
	 */
	public function newPdfPage()
	{
		//echo $this->size;
		//$this->size = $size;
		$this->pdf->pages[] = $this->pdf->newPage(Zend_Pdf_Page::SIZE_A4); //$this->size);
	}

	/**
	 * Method to remove a Page from the PDF
	 *
	 * @param array_key $id
	 * @return TRUE on success
	 */
	public function removePage($id)
	{
		// Remove specified page.
		unset($this->pdf->pages[$id]);
		return TRUE;
	}

	/**
	 * Styles manipulation methods
	 *
	 */

	public function newStyle()
	{
		$this->style = new Zend_Pdf_Style();
		return TRUE;
	}

	public function setFillColour($r, $g, $b)
	{
		$this->style->setFillColor(new Zend_Pdf_Color_RGB($r,$g,$b));
	}

	public function setLineColour($greylevel)
	{
		$this->style->setLineColor(new Zend_Pdf_Color_GrayScale($greylevel));
	}

	public function setLineWidths($width)
	{
		$this->style->setLineWidth($width);
	}

	public function setLineDash($array, $float)
	{
		$this->style->setLineDashingPattern($arr, $float);
	}

	public function setFont($font, $size)
	{
		$this->style->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES_ROMAN), 32);
	}




	public function dotest()
	{
		// Reverse page order
		$this->pdf->pages = array_reverse($this->pdf->pages);

		// Create new Style
		$style = new Zend_Pdf_Style();
		$style->setFillColor(new Zend_Pdf_Color_RGB(0, 0, 0.9));
		$style->setLineColor(new Zend_Pdf_Color_GrayScale(0.2));
		$style->setLineWidth(3);
		$style->setLineDashingPattern(array(3, 2, 3, 4), 1.6);
		$style->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA_BOLD), 32);

		// Create new image object
		$stampImage = Zend_Pdf_ImageFactory::factory('/var/www/stamp.jpg');

		// Mark page as modified
		foreach ($this->pdf->pages as $page){
			$page->saveGS();
			$page->setStyle($style);
			$page->rotate(0, 0, M_PI_2/3);

			$page->saveGS();
			$page->clipCircle(550, -10, 50);
			$page->drawImage($stampImage, 500, -60, 600, 40);
			$page->restoreGS();

			$page->drawText('Modified by Chisimba', 150, 0);
			$page->restoreGS();
		}

		// Add new page generated by Zend_Pdf object (page is attached to the specified the document)
		$this->pdf->pages[] = ($page1 = $this->pdf->newPage('A4'));

		// Add new page generated by Zend_Pdf_Page object (page is not attached to the document)
		$this->pdf->pages[] = ($page2 = new Zend_Pdf_Page(Zend_Pdf_Page::SIZE_LETTER_LANDSCAPE));

		// Create new font
		$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA);

		// Apply font and draw text
		$page1->setFont($font, 36);
		$page1->setFillColor(Zend_Pdf_Color_HTML::color('#9999cc'));
		$page1->drawText('Helvetica 36 text string', 60, 500);

		// Use font object for another page
		$page2->setFont($font, 24);
		$page2->drawText('Helvetica 24 text string', 60, 500);

		// Use another font
		$page2->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES), 32);
		$page2->drawText('Times-Roman 32 text string', 60, 450);

		// Draw rectangle
		$page2->setFillColor(new Zend_Pdf_Color_GrayScale(0.8));
		$page2->setLineColor(new Zend_Pdf_Color_GrayScale(0.2));
		$page2->setLineDashingPattern(array(3, 2, 3, 4), 1.6);
		$page2->drawRectangle(60, 400, 400, 350);

		// Draw circle
		$page2->setLineDashingPattern(Zend_Pdf_Page::LINE_DASHING_SOLID);
		$page2->setFillColor(new Zend_Pdf_Color_RGB(1, 0, 0));
		$page2->drawCircle(85, 375, 25);

		// Draw sectors
		$page2->drawCircle(200, 375, 25, 2*M_PI/3, -M_PI/6);
		$page2->setFillColor(new Zend_Pdf_Color_CMYK(1, 0, 0, 0));
		$page2->drawCircle(200, 375, 25, M_PI/6, 2*M_PI/3);
		$page2->setFillColor(new Zend_Pdf_Color_RGB(1, 1, 0));
		$page2->drawCircle(200, 375, 25, -M_PI/6, M_PI/6);

		// Draw ellipse
		$page2->setFillColor(new Zend_Pdf_Color_RGB(1, 0, 0));
		$page2->drawEllipse(250, 400, 400, 350);
		$page2->setFillColor(new Zend_Pdf_Color_CMYK(1, 0, 0, 0));
		$page2->drawEllipse(250, 400, 400, 350, M_PI/6, 2*M_PI/3);
		$page2->setFillColor(new Zend_Pdf_Color_RGB(1, 1, 0));
		$page2->drawEllipse(250, 400, 400, 350, -M_PI/6, M_PI/6);

		// Draw and fill polygon
		$page2->setFillColor(new Zend_Pdf_Color_RGB(1, 0, 1));
		$x = array();
		$y = array();
		for ($count = 0; $count < 8; $count++) {
			$x[] = 140 + 25*cos(3*M_PI_4*$count);
			$y[] = 375 + 25*sin(3*M_PI_4*$count);
		}
		$page2->drawPolygon($x, $y,
		Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE,
		Zend_Pdf_Page::FILL_METHOD_EVEN_ODD);

		// Draw line
		$page2->setLineWidth(0.5);
		$page2->drawLine(60, 375, 400, 375);

		$this->pdf->save('/var/www/chi/test.pdf', true /* update */);
	}


}