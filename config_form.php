<div class="field">
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_use_gregjul_prefixes', __('Use Gregorian / Julian Prefixes')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('Check this if you want to use [G] / [J] prefixes to indicate that a date '
										.'is meant to be specifying a Gregorian or a Julian date or timespan.');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('date_search_use_gregjul_prefixes', null, array('checked' => $useGregJulPrexifes)); ?>
    </div>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_search_all_fields', __('Scan All Text Fields')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('Check this if you want date / timespan processing to be carried out within all of an item\'s text fields.');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('date_search_search_all_fields', null, array('checked' => $searchAllFields)); ?>
    </div>
	<div id="shownHiddenSeachAll">
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_limit_fields', __('Limit Scan to Fields')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('Please select the elements i.e. fields that the scan for dates should be limited to.<br>'.
										'<em>Please note:</em> To select multiple entries, try holding '.
										'the Ctrl key (Windows) or the Cmd key (Mac) while clicking.');
            ?>
        </p>
				<?php echo get_view()->formSelect('date_search_limit_fields', $LimitFields, array('multiple' => true, 'size' => 10), $searchElements); ?>
		</div>
	<?php if ($withRelComments): ?>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_search_rel_comments', __('Scan Inside Relationship Comments')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
            echo __('The Item Relationships add-on is installed, and it has been patched to feature relationship comments. '.
										'Check this if you want Date Search to scan inside relationship comments.');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('date_search_search_rel_comments', null, array('checked' => $searchRelComments)); ?>
		</div>
	<?php else: ?>
		<input type="hidden" name="date_search_search_rel_comments" id="date_search_search_rel_comments" value="<?php echo $searchRelComments; ?>"> 
	<?php endif;?>
	</div>

<script type="text/javascript">
// <!--
	jQuery(document).ready(function() {
	
		var $ = jQuery; // use noConflict version of jQuery as the short $ within this block
	
		showHideShownHiddenSearchAll();
	
		$("#date_search_search_all_fields").change( function() { showHideShownHiddenSearchAll(); } );
	
		function showHideShownHiddenSearchAll() {
			var searchAllPreset = $("#date_search_search_all_fields").is(":checked");
			// alert("foo: "+searchAllPreset);
			if (searchAllPreset) { $("#shownHiddenSeachAll").hide(); } else { $("#shownHiddenSeachAll").show(); }
		}
	
	} );
// -->
</script>

</div>
