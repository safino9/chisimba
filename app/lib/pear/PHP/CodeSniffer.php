<?php
/**
 * PHP_CodeSniffer tokenises PHP code and detects violations of a
 * defined set of coding standards.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   http://matrix.squiz.net/developer/tools/php_cs/licence BSD Licence
 * @version   CVS: $Id$
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

require_once 'PHP/CodeSniffer/File.php';
require_once 'PHP/CodeSniffer/Tokens.php';
require_once 'PHP/CodeSniffer/Sniff.php';
require_once 'PHP/CodeSniffer/Exception.php';

/**
 * PHP_CodeSniffer tokenises PHP code and detects violations of a
 * defined set of coding standards.
 *
 * Standards are specified by classes that implement the PHP_CodeSniffer_Sniff
 * interface. A sniff registers what token types it wishes to listen for, then
 * PHP_CodeSniffer encounters that token, the sniff is invoked and passed
 * information about where the token was found in the stack, and the token stack
 * itself.
 *
 * Sniff files and their containing class must be prefixed with Sniff, and
 * have an extension of .php.
 *
 * Multiple PHP_CodeSniffer operations can be performed by re-calling the
 * process function with different parameters.
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
class PHP_CodeSniffer
{

    /**
     * The file or directory that is currently being processed.
     *
     * @var string
     */
    private $_file = array();

    /**
     * The directory where to search for tests.
     *
     * @var string
     */
    private $_standardDir = '';

    /**
     * The files that have been processed.
     *
     * @var array(PHP_CodeSniffer_FILE)
     */
    private $_files = array();

    /**
     * The listeners array.
     *
     * @var array(PHP_CodeSniffer_Sniff)
     */
    private $_listeners = array();


    /**
     * An array of extensions for files we will check.
     *
     * @var array
     */
    private $_allowedFileExtensions = array(
                                       'php',
                                       'inc',
                                      );

    /**
     * An array of variable types for param/var we will check.
     *
     * @var array(string)
     */
    public static $allowedTypes = array(
                                     'array',
                                     'boolean',
                                     'float',
                                     'integer',
                                     'mixed',
                                     'object',
                                     'string',
                                    );


    /**
     * Constructs a PHP_CodeSniffer object.
     *
     * @param int $verbosity The verbosity level.
     *                       1: Print progress information.
     *                       2: Print developer debug information.
     *
     * @see process()
     */
    public function __construct($verbosity=0)
    {
        define('PHP_CODESNIFFER_VERBOSITY', $verbosity);

    }//end __construct()


    /**
     * Sets an array of file extensions that we will allow checking of.
     *
     * @param array $extensions An array of file extensions.
     *
     * @return void
     */
    public function setAllowedFileExtensions(array $extensions)
    {
        $this->_allowedFileExtensions = $extensions;

    }//end setAllowedFileExtensions()


    /**
     * Processes the files/directories that PHP_CodeSniffer was constructed with.
     *
     * @param string|array $files    The files and directories to process. For
     *                               directories, each sub directory will also
     *                               be traversed for source files.
     * @param string       $standard The set of code sniffs we are testing
     *                               against.
     * @param array        $sniffs   The sniff names to restrict the allowed
     *                               listeners to.
     * @param boolean      $local    If true, don't recurse into directories.
     *
     * @return void
     * @throws PHP_CodeSniffer_Exception If files or standard are invalid.
     */
    public function process($files, $standard, array $sniffs=array(), $local=false)
    {
        if (is_array($files) === false) {
            if (is_string($files) === false || $files === null) {
                throw new PHP_CodeSniffer_Exception('$file must be a string');
            }

            $files = array($files);
        }

        if (is_string($standard) === false || $standard === null) {
            throw new PHP_CodeSniffer_Exception('$standard must be a string');
        }

        $this->_standardDir = realpath(dirname(__FILE__).'/CodeSniffer/Standards/'.$standard);

        // Reset the members.
        $this->_listeners = array();
        $this->_files     = array();

        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            echo 'Registering sniffs... ';
        }

        $this->_registerTokenListeners($standard, $sniffs);
        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            echo 'DONE'.PHP_EOL;
        }

        foreach ($files as $file) {
            $this->_file = $file;
            if (is_dir($this->_file) === true) {
                $this->_processFiles($this->_file, $local);
            } else {
                $this->_processFile($this->_file);
            }
        }

    }//end process()


    /**
     * Registers installed sniffs in the coding standard being used.
     *
     * Traverses the standard directory for classes that implement the
     * PHP_CodeSniffer_Sniff interface asks them to register. Each of the
     * sniff's class names must be exact as the basename of the sniff file.
     *
     * @param string $standard The name of the coding standard we are checking.
     * @param array  $sniffs   The sniff names to restrict the allowed
     *                         listeners to.
     *
     * @return void
     * @throws PHP_CodeSniffer_Exception If any of the tests failed in the
     *                                   registration process.
     */
    private function _registerTokenListeners($standard, array $sniffs=array())
    {
        $files = $this->_getSniffFiles($this->_standardDir, $standard);

        if (empty($sniffs) === false) {
            // Convert the allowed sniffs to lower case so
            // that its easier to check.
            foreach ($sniffs as &$sniff) {
                $sniff = strtolower($sniff);
            }
        }

        $csPath = 'PHP'.DIRECTORY_SEPARATOR.'CodeSniffer'.DIRECTORY_SEPARATOR;

        foreach ($files as $file) {

            if (strpos($file, $csPath) === false) {
                continue;
            }

            $className = substr($file, strpos($file, $csPath));
            $className = substr($className, 26);
            $className = substr($className, 0, -4);
            $className = str_replace(DIRECTORY_SEPARATOR, '_', $className);

            include_once $file;

            // If they have specified a list of sniffs to restrict to, check
            // to see if this sniff is allowed.
            $allowed = in_array(strtolower($className), $sniffs);
            if (empty($sniffs) === false && $allowed === false) {
                continue;
            }

            $this->_listeners[] = $className;
        }//end foreach

    }//end _registerTokenListeners()


    /**
     * Return a list of sniffs that a coding standard has defined.
     *
     * Sniffs are found by recursing the standard directory and also by
     * asking the standard for included sniffs.
     *
     * @param string $dir      The directory where to look for the files.
     * @param string $standard The name of the coding standard. If NULL, no
     *                         included sniffs will be checked for.
     *
     * @return array
     * @throws Exception If there was an error opening the directory.
     */
    private function _getSniffFiles($dir, $standard=null)
    {
        $di    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $files = array();

        foreach ($di as $file) {
            // Skip hidden files.
            if (substr($file->getFilename(), 0, 1) === '.') {
                continue;
            }

            // We are only interested in PHP and sniff files.
            $fileParts = explode('.', $file);
            if (array_pop($fileParts) !== 'php') {
                continue;
            }

            $basename = basename($file, '.php');
            if (substr($basename, -5) !== 'Sniff') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        // Load the standard class and ask it for a list of external
        // sniffs to include in the standard.
        if ($standard !== null && is_file("$dir/{$standard}CodingStandard.php") === true) {
            include_once "$dir/{$standard}CodingStandard.php";
            $standardClassName = "PHP_CodeSniffer_Standards_{$standard}_{$standard}CodingStandard";
            $standardClass     = new $standardClassName;

            $includedSniffs = $standardClass->getIncludedSniffs();
            foreach ($includedSniffs as $sniff) {
                $sniffDir = realpath(dirname(__FILE__)."/CodeSniffer/Standards/$sniff");
                if (is_dir($sniffDir) === true) {
                    if (PHP_CodeSniffer::isInstalledStandard($sniff) === true) {
                        // We are including a whole coding standard.
                        $files += $this->_getSniffFiles($sniffDir, $sniff);
                    } else {
                        // We are including a whole directory of sniffs.
                        $files += $this->_getSniffFiles($sniffDir);
                    }
                } else {
                    if (substr($sniffDir, -5) !== 'Sniff') {
                        continue;
                    }

                    $files[] = "$sniffDir.php";
                }
            }
        }//end if

        return $files;

    }//end _getSniffFiles()


    /**
     * Run the code sniffs over each file in a given directory.
     *
     * Recusively reads the specified directory and performs the PHP_CodeSniffer
     * sniffs on each source file found within the directories.
     *
     * @param string  $dir   The directory to process.
     * @param boolean $local If true, only process files in this directory, not
     *                       sub directories.
     *
     * @return void
     * @throws Exception If there was an error opening the directory.
     */
    private function _processFiles($dir, $local=false)
    {
        if ($local === true) {
            $di = new DirectoryIterator($dir);
        } else {
            $di = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        }

        foreach ($di as $file) {
            $filePath = realpath($file->getPathname());

            if (is_dir($filePath) === true) {
                continue;
            }

            // Check that the file's extension is one we are checking.
            // Note that because we are doing a whole directory, we
            // are strick about checking the extension and we don't
            // let files with no extension through.
            $fileParts = explode('.', $file);
            $extension = array_pop($fileParts);
            if ($extension === $file) {
                continue;
            }

            if (in_array($extension, $this->_allowedFileExtensions) === false) {
                continue;
            }

            $this->_processFile($filePath);
        }

    }//end _processFiles()


    /**
     * Run the code sniffs over a signle given file.
     *
     * Processes the file and runs the PHP_CodeSniffer sniffs to verify that it
     * conforms with the standard.
     *
     * @param string $file The file to process.
     *
     * @return void
     * @throws PHP_CodeSniffer_Exception If the file could not be processed.
     */
    private function _processFile($file)
    {
        $file = realpath($file);

        if (file_exists($file) === false) {
            throw new PHP_CodeSniffer_Exception("Source file $file does not exist");
        }

        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            $startTime = time();
            echo 'Processing '.basename($file).' ';
            if (PHP_CODESNIFFER_VERBOSITY > 1) {
                echo PHP_EOL;
            }
        }

        $phpcsFile      = new PHP_CodeSniffer_File($file, $this->_listeners);
        $this->_files[] = $phpcsFile;
        $phpcsFile->start();

        if (PHP_CODESNIFFER_VERBOSITY > 0) {
            $timeTaken = (time() - $startTime);
            if ($timeTaken === 0) {
                echo 'DONE in < 1 second';
            } else if ($timeTaken === 1) {
                echo 'DONE in 1 second';
            } else {
                echo "DONE in $timeTaken seconds";
            }

            $errors   = $phpcsFile->getErrorCount();
            $warnings = $phpcsFile->getWarningCount();
            echo " ($errors errors, $warnings warnings)".PHP_EOL;
        }

    }//end _processFile()


    /**
     * Prints all errors and warnings for each file processed.
     *
     * Errors and warnings are displayed together, grouped by file.
     *
     * @param boolean $showWarnings Show warnings as well as errors.
     *
     * @return int The number of error and warning messages shown.
     */
    public function printErrorReport($showWarnings=true)
    {
        $errorsShown = 0;

        foreach ($this->_files as $file) {
            $warnings    = $file->getWarnings();
            $errors      = $file->getErrors();
            $numWarnings = $file->getWarningCount();
            $numErrors   = $file->getErrorCount();
            $filename    = $file->getFilename();

            if ($numErrors === 0 && $numWarnings === 0) {
                // Prefect score!
                continue;
            }

            if ($numErrors === 0 && $showWarnings === false) {
                // Prefect score (sort of).
                continue;
            }

            // Merge errors and warnings.
            foreach ($errors as $line => $lineErrors) {
                $newErrors = array();
                foreach ($lineErrors as $message) {
                    $newErrors[] = array(
                                    'message' => $message,
                                    'type'    => 'ERROR',
                                   );
                }

                $errors[$line] = $newErrors;
            }

            if ($showWarnings === true) {
                foreach ($warnings as $line => $lineWarnings) {
                    $newWarnings = array();
                    foreach ($lineWarnings as $message) {
                        $newWarnings[] = array(
                                          'message' => $message,
                                          'type'    => 'WARNING',
                                         );
                    }

                    if (isset($errors[$line]) === true) {
                        $errors[$line] = array_merge($newWarnings, $errors[$line]);
                    } else {
                        $errors[$line] = $newWarnings;
                    }
                }
            }

            ksort($errors);

            echo PHP_EOL.'FILE: ';
            if (strlen($filename) <= 71) {
                echo $filename;
            } else {
                echo '...'.substr($filename, (strlen($filename) - 71));
            }

            echo PHP_EOL;
            echo str_repeat('-', 80).PHP_EOL;
            $numLines = count($errors);
            echo "FOUND $numErrors ERROR(S) ";

            if ($showWarnings === true) {
                echo "AND $numWarnings WARNING(S) ";
            }

            echo "AFFECTING $numLines LINE(S)".PHP_EOL;
            echo str_repeat('-', 80).PHP_EOL;

            // Work out the max line number for formatting.
            $maxLine = 0;
            foreach ($errors as $line => $lineErrors) {
                if ($line > $maxLine) {
                    $maxLine = $line;
                }
            }

            $maxLineLength = strlen($maxLine);

            // The length of the word ERROR or WARNING; used for padding.
            if ($showWarnings === true && $numWarnings > 0) {
                $typeLength = 7;
            } else {
                $typeLength = 5;
            }

            // The padding that all lines will require that are
            // printing an error message overflow.
            $paddingLine2  = str_repeat(' ', ($maxLineLength + 1));
            $paddingLine2 .= ' | ';
            $paddingLine2 .= str_repeat(' ', $typeLength);
            $paddingLine2 .= ' | ';

            // The maxium amount of space an error message can use.
            $maxErrorSpace = (80 - strlen($paddingLine2));

            foreach ($errors as $line => $lineErrors) {
                foreach ($lineErrors as $error) {
                    // The padding that goes on the front of the line.
                    $padding  = ($maxLineLength - strlen($line));
                    $errorMsg = wordwrap($error['message'], $maxErrorSpace, PHP_EOL."$paddingLine2");

                    echo ' '.str_repeat(' ', $padding).$line.' | '.$error['type'];
                    if ($error['type'] === 'ERROR') {
                        if ($showWarnings === true && $numWarnings > 0) {
                            echo '  ';
                        }
                    }

                    echo ' | '.$errorMsg.PHP_EOL;
                    $errorsShown++;
                }
            }//end foreach

            echo str_repeat('-', 80).PHP_EOL.PHP_EOL;

        }//end foreach

        return $errorsShown;

    }//end printErrorReport()


    /**
     * Prints a summary of errors and warnings for each file processed.
     *
     * If verbose output is enabled, results are shown for all files, even if
     * they have no errors or warnings. If verbose output is disabled, we only
     * show files that have at least one warning or error.
     *
     * @param boolean $showWarnings Show warnings as well as errors.
     *
     * @return int The number of error and warning messages shown.
     */
    public function printErrorReportSummary($showWarnings=true)
    {
        $errorFiles = array();

        foreach ($this->_files as $file) {
            $numWarnings = $file->getWarningCount();
            $numErrors   = $file->getErrorCount();
            $filename    = $file->getFilename();

            // If verbose output is enabled, we show the results for all files,
            // but if not, we only show files that had errors or warnings.
            if (PHP_CODESNIFFER_VERBOSITY > 0 || $numErrors > 0 || ($numWarnings > 0 && $showWarnings === true)) {
                $errorFiles[$filename] = array(
                                          'warnings' => $numWarnings,
                                          'errors'   => $numErrors,
                                         );
            }
        }

        if (empty($errorFiles) === true) {
            // Nothing to print.
            return 0;
        }

        echo PHP_EOL.'PHP CODE SNIFFER REPORT SUMMARY'.PHP_EOL;
        echo str_repeat('-', 80).PHP_EOL;
        if ($showWarnings === true) {
            echo 'FILE'.str_repeat(' ', 60).'ERRORS  WARNINGS'.PHP_EOL;
        } else {
            echo 'FILE'.str_repeat(' ', 70).'ERRORS'.PHP_EOL;
        }

        echo str_repeat('-', 80).PHP_EOL;

        $totalErrors   = 0;
        $totalWarnings = 0;
        $totalFiles    = 0;

        foreach ($errorFiles as $file => $errors) {
            if ($showWarnings === true) {
                $padding = (62 - strlen($file));
            } else {
                $padding = (72 - strlen($file));
            }

            if ($padding < 0) {
                $file    = '...'.substr($file, (($padding * -1) + 3));
                $padding = 0;
            }

            echo $file.str_repeat(' ', $padding).'  ';
            echo $errors['errors'];
            if ($showWarnings === true) {
                echo str_repeat(' ', (8 - strlen((string) $errors['errors'])));
                echo $errors['warnings'];
            }

            echo PHP_EOL;

            $totalErrors   += $errors['errors'];
            $totalWarnings += $errors['warnings'];
            $totalFiles++;
        }//end foreach

        echo str_repeat('-', 80).PHP_EOL;
        echo "A TOTAL OF $totalErrors ERROR(S) ";
        if ($showWarnings === true) {
            echo "AND $totalWarnings WARNING(S) ";
        }

        echo "WERE FOUND IN $totalFiles FILE(S)".PHP_EOL;
        echo str_repeat('-', 80).PHP_EOL.PHP_EOL;

        return ($totalErrors + $totalWarnings);

    }//end printErrorReportSummary()


    /**
     * Returns the PHP_CodeSniffer file objects.
     *
     * @return array(PHP_CodeSniffer_File)
     */
    public function getFiles()
    {
        return $this->_files;

    }//end getFiles()


    /**
     * Takes a token produced from <code>token_get_all()</code> and produces a
     * more uniform token.
     *
     * Note that this method also resolves T_STRING tokens into more descrete
     * types, therefore there is no need to call resolveTstringToken()
     *
     * @param string|array $token The token to convert.
     *
     * @return array The new token.
     */
    public static function standardiseToken($token)
    {
        if (is_array($token) === false) {
            $newToken = self::resolveSimpleToken($token);
        } else {
            // Some T_STRING tokens can be more specific.
            if ($token[0] === T_STRING) {
                $newToken = self::resolveTstringToken($token);
            } else {
                $newToken            = array();
                $newToken['code']    = $token[0];
                $newToken['content'] = $token[1];
                $newToken['type']    = token_name($token[0]);
            }
        }

        return $newToken;

    }//end standardiseToken()


    /**
     * Converts T_STRING tokens into more usable token names.
     *
     * The token should be produced using the token_get_all() function.
     * Currently, not all T_STRING tokens are converted.
     *
     * @param string|array $token The T_STRING token to convert as constructed
     *                            by token_get_all().
     *
     * @return array The new token.
     */
    public static function resolveTstringToken(array $token)
    {
        $newToken = array();
        switch (strtolower($token[1])) {
        case 'false':
            $newToken['type'] = 'T_FALSE';
            break;
        case 'true':
            $newToken['type'] = 'T_TRUE';
            break;
        case 'null':
            $newToken['type'] = 'T_FALSE';
            break;
        case 'self':
            $newToken['type'] = 'T_SELF';
            break;
        case 'parent':
            $newToken['type'] = 'T_PARENT';
            break;
        default:
            $newToken['type'] = 'T_STRING';
            break;
        }

        $newToken['code']    = constant($newToken['type']);
        $newToken['content'] = $token[1];

        return $newToken;

    }//end resolveTstringToken()


    /**
     * Converts simple tokens into a format that conforms to complex tokens
     * produced by token_get_all().
     *
     * Simple tokens are tokens that are not in array form when produced from
     * token_get_all().
     *
     * @param string $token The simple token to convert.
     *
     * @return array The new token in array format.
     */
    public static function resolveSimpleToken($token)
    {
        $newToken = array();

        switch ($token) {
        case '{':
            $newToken['type'] = 'T_OPEN_CURLY_BRACKET';
            break;
        case '}':
            $newToken['type'] = 'T_CLOSE_CURLY_BRACKET';
            break;
        case '[':
            $newToken['type'] = 'T_OPEN_SQUARE_BRACKET';
            break;
        case ']':
            $newToken['type'] = 'T_CLOSE_SQUARE_BRACKET';
            break;
        case '(':
            $newToken['type'] = 'T_OPEN_PARENTHESIS';
            break;
        case ')':
            $newToken['type'] = 'T_CLOSE_PARENTHESIS';
            break;
        case ':':
            $newToken['type'] = 'T_COLON';
            break;
        case '.':
            $newToken['type'] = 'T_STRING_CONCAT';
            break;
        case '?':
            $newToken['type'] = 'T_INLINE_THEN';
            break;
        case ';':
            $newToken['type'] = 'T_SEMICOLON';
            break;
        case '=':
            $newToken['type'] = 'T_EQUAL';
            break;
        case '*':
            $newToken['type'] = 'T_MULTIPLY';
            break;
        case '/':
            $newToken['type'] = 'T_DIVIDE';
            break;
        case '+':
            $newToken['type'] = 'T_PLUS';
            break;
        case '-':
            $newToken['type'] = 'T_MINUS';
            break;
        case '%':
            $newToken['type'] = 'T_MODULUS';
            break;
        case '^':
            $newToken['type'] = 'T_POWER';
            break;
        case '&':
            $newToken['type'] = 'T_BITWISE_AND';
            break;
        case '|':
            $newToken['type'] = 'T_BITWISE_OR';
            break;
        case '<':
            $newToken['type'] = 'T_LESS_THAN';
            break;
        case '>':
            $newToken['type'] = 'T_GREATER_THAN';
            break;
        case '!':
            $newToken['type'] = 'T_BOOLEAN_NOT';
            break;
        case ',':
            $newToken['type'] = 'T_COMMA';
            break;
        default:
            $newToken['type'] = 'T_NONE';
            break;

        }//end switch

        $newToken['code']    = constant($newToken['type']);
        $newToken['content'] = $token;

        return $newToken;

    }//end resolveSimpleToken()


    /**
     * Returns true if the specified string is in the camel caps format.
     *
     * @param string  $string      The string the verify.
     * @param boolean $classFormat If true, check to see if the string is in the
     *                             class format. Class format strings must start
     *                             with a capital letter and contain no
     *                             underscores.
     * @param boolean $public      If true, the first character in the string
     *                             must be an a-z character. If false, the
     *                             character must be an underscore. This
     *                             argument is only applicable if $classFormat
     *                             is false.
     * @param boolean $strict      If true, the string must not have two captial
     *                             letters next to each other. If false, a
     *                             relaxed camel caps policy is used to allow
     *                             for acronyms.
     *
     * @return boolean
     */
    public static function isCamelCaps($string, $classFormat=false, $public=true, $strict=true)
    {
        // Check the first character first.
        if ($classFormat === false) {
            if ($public === false) {
                $legalFirstChar = '[_][a-z]';
            } else {
                $legalFirstChar = '[a-z]';
            }
        } else {
            $legalFirstChar = '[A-Z]';
        }

        if (preg_match("|^$legalFirstChar|", $string) === 0) {
            return false;
        }

        // Check that the name only contains legal characters.
        if ($classFormat === false) {
            $legalChars = 'a-zA-Z0-9';
        } else {
            $legalChars =  'a-zA-Z';
        }

        if (preg_match("|[^$legalChars]|", substr($string, 1)) > 0) {
            return false;
        }

        if ($strict === true) {
            // Check that there are not two captial letters next to each other.
            $length          = strlen($string);
            $lastCharWasCaps = ($classFormat === false) ? false : true;

            for ($i = 1; $i < $length; $i++) {
                $isCaps = (strtoupper($string{$i}) === $string{$i}) ? true : false;
                if ($isCaps === true && $lastCharWasCaps === true) {
                    return false;
                }

                $lastCharWasCaps = $isCaps;
            }
        }

        return true;

    }//end isCamelCaps()


    /**
     * Returns true if the specified string is in the underscore caps format.
     *
     * @param string $string The string to verify.
     *
     * @return boolean
     */
    public static function isUnderscoreName($string)
    {
        $validName = true;
        $nameBits  = explode('_', $string);

        if (preg_match('|^[A-Z]|', $string) === 0) {
            // Name does not begin with a capital letter.
            $validName = false;
        } else {
            foreach ($nameBits as $bit) {
                if ($bit{0} !== strtoupper($bit{0})) {
                    $validName = false;
                    break;
                }
            }
        }

        return $validName;

    }//end isUnderscoreName()


    /**
     * Returns a valid variable type for param/var tag.
     *
     * If type is not one of the standard type, it must be a custom type.
     * Returns the correct type name suggestion if type name is invalid.
     *
     * @param string $varType The variable type to process.
     *
     * @return string
     */
    public static function suggestType($varType)
    {
        if ($varType === '') {
            return '';
        }

        if (in_array($varType, self::$allowedTypes) === true) {
            return $varType;
        } else {
            $lowerVarType = strtolower($varType);
            switch ($lowerVarType) {
            case 'bool':
                return 'boolean';
            case 'double':
            case 'real':
                return 'float';
            case 'int':
                return 'integer';
            case 'array()':
                return 'array';
            }//end switch

            if (strpos($lowerVarType, 'array(') !== false) {
                // Valid array declaration: array, array(type), array(type1 => type2).
                $matches = array();
                if (preg_match('/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i', $varType, $matches) !== 0) {
                    $type1 = (isset($matches[1]) === true) ? $matches[1] : '';
                    $type2 = (isset($matches[3]) === true) ? $matches[3] : '';
                    $type1 = self::suggestType($type1);
                    $type2 = self::suggestType($type2);
                    if ($type2 !== '') {
                        $type2 = ' => '.$type2;
                    }
                    return "array($type1$type2)";
                } else {
                    return 'array';
                }
            } else if (in_array($lowerVarType, self::$allowedTypes) === true) {
                // A valid type, but not lower cased.
                return $lowerVarType;
            } else {
                // Must be a custom type name.
                return $varType;
            }//end if

        }//end if

    }//end suggestType()


    /**
     * Get a list of all coding standards installed.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a Sniffs subdirectory.
     *
     * @param boolean $includeGeneric If true, the special "Generic"
     *                                coding standard will be included
     *                                if installed.
     *
     * @return array
     * @see isInstalledStandard()
     */
    public static function getInstalledStandards($includeGeneric=false)
    {
        $installedStandards = array();
        $standardsDir       = dirname(__FILE__).'/CodeSniffer/Standards';

        $di = new DirectoryIterator($standardsDir);
        foreach ($di as $file) {
            if ($file->isDir() === true && $file->isDot() === false) {
                $filename = $file->getFilename();

                // Ignore the special "Generic" standard.
                if ($includeGeneric === false && $filename === 'Generic') {
                    continue;
                }

                // Valid coding standard dirs include a standard class.
                if (is_file($file->getPathname()."/{$filename}CodingStandard.php") === true) {
                    // We found a coding standard directory.
                    $installedStandards[] = $filename;
                }
            }
        }

        return $installedStandards;

    }//end getInstalledStandards()


    /**
     * Determine if a standard is installed.
     *
     * Coding standards are directories located in the
     * CodeSniffer/Standards directory. Valid coding standards
     * include a Sniffs subdirectory.
     *
     * @param string $standard The name of the coding standard.
     *
     * @return boolean
     * @see getInstalledStandards()
     */
    public static function isInstalledStandard($standard)
    {
        $standardDir  = dirname(__FILE__);
        $standardDir .= '/CodeSniffer/Standards/'.$standard;
        return (is_file("$standardDir/{$standard}CodingStandard.php") === true);

    }//end isInstalledStandard()


}//end class

?>
