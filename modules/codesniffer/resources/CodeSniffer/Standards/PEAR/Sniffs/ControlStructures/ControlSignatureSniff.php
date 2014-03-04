<?php
/**
 * Verifies that control statements conform to their coding standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id: ControlSignatureSniff.php 6507 2007-06-06 12:50:00Z paulscott $
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

require_once 'PHP/CodeSniffer/Standards/AbstractPatternSniff.php';

/**
 * Verifies that control statements conform to their coding standards.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   Release: 0.6.0
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PEAR_Sniffs_ControlStructures_ControlSignatureSniff extends PHP_CodeSniffer_Standards_AbstractPatternSniff
{


    /**
     * Constructs a PEAR_Sniffs_ControlStructures_ControlSignatureSniff.
     */
    public function __construct()
    {
        parent::__construct(true);

    }//end __construct()


    /**
     * Returns the patterns that this test wishes to verify.
     *
     * @return array(string)
     */
    protected function getPatterns()
    {

        return array(
                "do {\n...} while (...);\n",
                "while (...) {\n",
                "for (...) {\n",
                "if (...) {\n",
                "foreach (...) {\n",
                "} else if (...) {\n",
                "} elseif (...) {\n",
                "} else {\n",
                "do {\n",
               );

    }//end getPatterns()


}//end class

?>