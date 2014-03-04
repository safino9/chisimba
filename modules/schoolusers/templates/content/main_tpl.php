<?php
/**
*  A main content template for schoolusers
*  Author: Kevin Cyster kcyster@gmail.com
*  Date: March 21, 2012, 9:18 pm
*
*/
ob_start();
$objFix = $this->getObject('cssfixlength', 'htmlelements');
$objFix->fixThree();
?>

<div id="twocolumn">
    <div id="Canvas_Content_Body_Region1">
        {
            "display" : "block",
            "module" : "schoolusers",
            "block" : "schoolusersleft"
        }
        <div id="leftdynamic_area" class="leftdynamic_area_layer"></div>
        <div id="leftfeedback_area" class="leftfeedback_area_layer"></div>
    </div>
    <div id="Canvas_Content_Body_Region2">
        {
            "display" : "block",
            "module" : "schoolusers",
            "block" : "schoolusersmain"
        }
        <div id="middledynamic_area" class="middledynamic_area_layer">&nbsp;</div>
        <div id="middlefeedback_area" class="middlefeedback_area_layer">&nbsp;</div>
    </div>
</div>
<?php
// Get the contents for the layout template
$pageContent = ob_get_contents();
ob_end_clean();
$this->setVar('pageContent', $pageContent);
?>