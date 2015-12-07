<div class="field">
    <div class="two columns alpha">
        <?php echo $this->formLabel('date_search_term', __('Date Search')); ?>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
        <?php
        echo __('You may enter a date in the forms YYYY, YYYY-MM, or YYYY-MM-DD, or a timespan consisting of '.
								'two dates, separated by a hypen ("-"). Date Search will find items that contain dates and timespans '.
								'matching your search. For example: "1970" will find an item mentioning the timespan "1960-1980".');
        ?>
        </p>
        <p>
            <?php echo $this->formText('date_search_term', @$_GET['date_search_term'], array('size' => 10)); ?>
        </p>
    </div>
</div>
