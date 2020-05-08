<div id="footer">
    <div id="footer-content" class="col-xs-12 col-sm-8">
        <a href="https://github.com/tekhnee/appointments">TekhneeAppointments</a>
            <?php
                echo $this->config->item('version') . ' ';

                $release_title = $this->config->item('release_label');
                if ($release_title != '') {
                    echo ' - ' . $release_title;
                }
            ?></a>
        | A fork of <a href="http://easyappointments.org/">Easy!Appointments</a> |
        <?= lang('licensed_under') ?> GPLv3 |
        <span id="select-language" class="label label-success">
			<?= $this->config->item('available_languages')[$this->config->item('language')][2] ?>
        </span>
        |
        <a href="<?= $this->config->item('base_url') ?>">
            <?= lang('go_to_booking_page') ?>
        </a>
    </div>

    <div id="footer-user-display-name" class="col-xs-12 col-sm-4">
        <?= lang('hello') . ', ' . $user_display_name ?>!
    </div>
</div>

<script src="<?= asset_url('assets/js/backend.js') ?>"></script>
<script src="<?= asset_url('assets/js/general_functions.js') ?>"></script>
</body>
</html>
