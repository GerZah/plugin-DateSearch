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
        <?php echo get_view()->formRadio('date_search_use_gregjul_prefixes', $useGregJulPrefixes, array(), array(0 => __("no"), 1 => __("yes") ,2 => __("optional")) ); ?>
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
	
		$("input[name=date_search_use_gregjul_prefixes]:radio").change(function () { activateReindexCheckbox(); } );
		$("#date_search_search_all_fields").change( function() { showHideShownHiddenSearchAll(); activateReindexCheckbox(); } );
		$("#date_search_limit_fields").change( function() { activateReindexCheckbox(); } );
		$("#date_search_search_rel_comments").change( function() { activateReindexCheckbox(); } );

		function showHideShownHiddenSearchAll() {
			var searchAllPreset = $("#date_search_search_all_fields").is(":checked");
			// alert("foo: "+searchAllPreset);
			if (searchAllPreset) { $("#shownHiddenSeachAll").slideUp(); } else { $("#shownHiddenSeachAll").slideDown(); }
		}

		function activateReindexCheckbox() { $("#date_search_trigger_reindex").prop('checked', true); }
	
	} );
// -->
</script>

<hr>

    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_trigger_reindex', __('Trigger Re-indexing of Existing Content')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php
							echo __('<strong>Please note:</strong> Checking this box will re-generate the index <em>now</em> and '.
											'exactly <em>once</em>. This action will be carried out as soon as you click on "Save Changes".');
            ?>
        </p>
        <?php echo get_view()->formCheckbox('date_search_trigger_reindex', null, array('checked' => false)); ?>
        <p class="explanation">
            <?php
							echo __('<em>Explanation:</em> Date Search relies on a search index that is being created during content'.
											' maintenance in the background. However, existing content will not be re-indexed automatically. '.
											'So if you have existing content or modify your settings, you should re-generate the search index.');
            ?>
        </p>
    </div>

  <?php if (isset($debugOutput)) { ?>
    <div class="two columns alpha">
        <?php echo get_view()->formLabel('date_search_debug_output', __('Debug Output')); ?>
    </div>
    <div class="inputs five columns omega">
        <?php echo get_view()->formCheckbox('date_search_debug_output', null, array('checked' => $debugOutput)); ?>
    </div>
  <?php } ?>

</div>
