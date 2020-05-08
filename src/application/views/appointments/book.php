<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#2C446D">

	<title><?= lang('page_title') . ' ' .  $company_name ?></title>

	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/ext/bootstrap/css/bootstrap.min.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/ext/jquery-ui/jquery-ui.min.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/ext/jquery-qtip/jquery.qtip.min.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/ext/cookieconsent/cookieconsent.min.css') ?>">
	<link rel='stylesheet' type="text/css" href="<?= asset_url('assets/ext/select2/select2.min.css') ?>" />
	<link rel='stylesheet' type="text/css" href="<?= asset_url('assets/ext/select2/select2-bootstrap.min.css') ?>" />
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/frontend.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/flag-icon/flag-icon.min.css') ?>">

	<link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
	<link rel="icon" sizes="192x192" href="<?= asset_url('assets/img/logo.png') ?>">
</head>

<body>
	<div id="main" class="container">
		<div class="wrapper row">
			<div id="book-appointment-wizard" class="col-xs-12 col-md-12 col-lg-12">

				<!-- FRAME TOP BAR -->

				<?php if ($manage_mode) : ?>
					<div id="cancel-appointment-frame" class="booking-header-bar row">
						<div class="col-xs-12 col-sm-10">
							<p><?= lang('cancel_appointment_hint') ?></p>
						</div>
						<div class="col-xs-12 col-sm-2">
							<form id="cancel-appointment-form" method="post" action="<?= site_url('appointments/cancel/' . $appointment_data['hash']) ?>">
								<input type="hidden" name="csrfToken" value="<?= $this->security->get_csrf_hash() ?>" />
								<textarea name="cancel_reason" style="display:none"></textarea>
								<button id="cancel-appointment" class="btn btn-danger btn-sm"><?= lang('cancel') ?></button>
							</form>
						</div>
					</div>
					<div class="booking-header-bar row">
						<div class="col-xs-12 col-sm-10">
							<p><?= lang('delete_personal_information_hint') ?></p>
						</div>
						<div class="col-xs-12 col-sm-2">
							<button id="delete-personal-information" class="btn btn-default btn-sm"><?= lang('delete') ?></button>
						</div>
					</div>
				<?php endif; ?>

				<?php
				if (isset($exceptions)) {
					echo '<div style="margin: 10px">';
					echo '<h4>' . lang('unexpected_issues') . '</h4>';
					foreach ($exceptions as $exception) {
						echo exceptionToHtml($exception);
					}
					echo '</div>';
				}
				?>

				<!-- SELECT SERVICE AND PROVIDER -->

				<div id="steps">
					<div id="step-1" class="book-step active-step" title="<?= lang('step_one_title') ?>">
						<strong>1</strong>
					</div>

					<div id="step-2" class="book-step" title="<?= lang('step_two_title') ?>">
						<strong>2</strong>
					</div>
					<div id="step-3" class="book-step" title="<?= lang('step_three_title') ?>">
						<strong>3</strong>
					</div>
				</div>
				<div id="wizard-frame-1" class="wizard-frame">
					<div class="frame-container">
						<h3 class="frame-title"><?= lang('step_one_title') ?></h3>

						<div class="frame-content">
							<div class="form-group">
								<div class="input-group">
									<span class="input-group-btn">
										<button class="btn btn-success btn-sm disabledbg-green prepend-glow" id="service-prepend-button" data-select2-open="single-prepend-text" disabled="disabled">
											<svg style="margin-top: 2px; margin-right: 3px; height: 18px; width: 18px;">
												<use xlink:href="/assets/sprites/solid.svg#leaf"></use>
											</svg>
										</button>
									</span>
									<select id="select-service" class="col-xs-12 col-sm-4 form-control">

										<!-- Required by Select2 for placeholder text: -->
										<!-- <option></option> -->

										<?php
										// Group services by category, only if there is at least one service with a parent category.
										$has_category = FALSE;
										foreach ($available_services as $service) {
											if ($service['category_id'] != NULL) {
												$has_category = TRUE;
												break;
											}
										}

										// Keep only services either without a local designation,
										// or beginning with a designation [xx], where xx is the TekhneeAppointments language code:
										$locale_descriptor_regex = '/\s*\[(.+?)\]\s*/';

										if ($has_category) {
											$grouped_services = array();

											foreach ($available_services as $service) {
												if ($service['category_id'] != NULL) {
													if (!isset($grouped_services[$service['category_name']])) {
														$grouped_services[$service['category_name']] = array();
													}

													$grouped_services[$service['category_name']][] = $service;
												}
											}

											// We need the uncategorized services at the end of the list so
											// we will use another iteration only for the uncategorized services.
											$grouped_services['uncategorized'] = array();
											foreach ($available_services as $service) {
												if ($service['category_id'] == NULL) {
													$grouped_services['uncategorized'][] = $service;
												}
											}

											foreach ($grouped_services as $key => $group) {
												$group_label = ($key != 'uncategorized')
													? $group[0]['category_name'] : 'Uncategorized';

												$category_locale_array = array();
												preg_match_all($locale_descriptor_regex, $group_label, $category_locale_array);
												$category_locales = $category_locale_array[1] ?? null;

												if ($category_locales === [] || in_array($this->config->item('language'), $category_locales)) {
													$group_label = preg_replace($locale_descriptor_regex, '', $group_label);

													if (count($group) > 0) {
														echo '<optgroup label="' . $group_label . '">';
														foreach ($group as $service) {
															echo '<option value="' . $service['id'] . '">'
																. $service['name'] . '</option>';
														}
														echo '</optgroup>';
													}
												}
											}
										} else {
											foreach ($available_services as $service) {
												echo '<option value="' . $service['id'] . '">' . $service['name'] . '</option>';
											}
										}
										?>
									</select>
								</div>
							</div>

							<div class="form-group">
								<div class="input-group">
									<span class="input-group-btn">
										<button class="btn btn-success btn-sm disabledbg-green prepend-glow" data-select2-open="single-prepend-text" disabled="disabled">
											<svg style="margin-top: 2px; margin-right: 3px; height: 18px; width: 18px;">
												<use xlink:href="/assets/sprites/solid.svg#user-tie"></use>
											</svg>
										</button>
									</span>
									<select id="select-provider" class="col-xs-12 col-sm-4 form-control">

									</select>
								</div>
							</div>
							<div class="command-buttons">
								<button type="button" id="button-next-1" class="btn button-next btn-primary" data-step_index="1">
									<?= lang('next') ?>
									<span class="glyphicon glyphicon-forward"></span>
								</button>
							</div>
							<div id="service-description" style="display:none;"></div>
						</div>
					</div>
				</div>

				<!-- SELECT APPOINTMENT DATE -->

				<div id="wizard-frame-2" class="wizard-frame" style="display:none;">
					<div class="frame-container">
						<h3 class="frame-title"><?= lang('step_two_title') ?></h3>
						<div id="date-time-pickers" class="frame-content row">
							<div class="col-xs-12 col-sm-6">
								<div id="select-date">
									<div id="appointment-details-1"></div>
									<!-- Date picker will be injected here. -->
								</div>
							</div>
							<div class="col-xs-12 col-sm-6 time-selector">
								<div id="available-hours"></div>
							</div>
						</div>
					</div>

					<div id="timezone-selector">
						<select class="js-Selector"></select>
					</div>

					<div class="command-buttons">
						<button type="button" id="button-back-2" class="btn button-back btn-default" data-step_index="2">
							<span class="glyphicon glyphicon-backward"></span>
							<?= lang('back') ?>
						</button>
						<button type="button" id="button-next-2" class="btn button-next btn-primary" data-step_index="2">
							<?= lang('next') ?>
							<span class="glyphicon glyphicon-forward"></span>
						</button>
					</div>
				</div>

				<!-- ENTER CUSTOMER DATA -->

				<div id="wizard-frame-3" class="wizard-frame" style="display:none;">
					<div class="frame-container">

						<h3 class="frame-title"><?= lang('step_three_title') ?></h3>

						<div class="frame-content row">
							<div class="col-xs-12 col-sm-6">
								<div class="form-group">
									<label for="first-name" class="control-label"><?= lang('first_name') ?> *</label>
									<input type="text" id="first-name" class="required form-control" maxlength="100" value="<?= $this->session->userdata('first-name'); ?>" />
								</div>
								<div class="form-group">
									<label for="last-name" class="control-label"><?= lang('last_name') ?> *</label>
									<input type="text" id="last-name" class="required form-control" maxlength="120" value="<?= $this->session->userdata('last-name'); ?>" />
								</div>
								<div class="form-group">
									<label for="email" class="control-label"><?= lang('email') ?> *</label>
									<input type="text" id="email" class="required form-control" maxlength="120" value="<?= $this->session->userdata('email'); ?>" />
								</div>
								<div class="form-group">
									<label for="phone-number" class="control-label"><?= lang('phone_number') ?>
										<?php echo ('1' === $require_phone_number) ? '*' : ''; ?></label>
									<input type="text" id="phone-number" class="<?php echo ('1' === $require_phone_number) ? 'required' : ''; ?>
                                        form-control" maxlength="60" />
									<div class="form-group">
										<label for="notes" class="control-label"><?= lang('notes') ?></label>
										<textarea id="notes" maxlength="500" class="form-control" rows="3"></textarea>
									</div>
								</div>
							</div>

							<div class="col-xs-12 col-sm-6">
								<!-- <div class="form-group">
                                    <label for="address" class="control-label"><?= lang('address') ?></label>
                                    <input type="text" id="address" class="form-control" maxlength="120" />
                                </div>
                                <div class="form-group">
                                    <label for="city" class="control-label"><?= lang('city') ?></label>
                                    <input type="text" id="city" class="form-control" maxlength="120" />
                                </div>
                                <div class="form-group">
                                    <label for="zip-code" class="control-label"><?= lang('zip_code') ?></label>
                                    <input type="text" id="zip-code" class="form-control" maxlength="120" />
                                </div>
                                <div class="form-group">
                                    <label for="notes" class="control-label"><?= lang('notes') ?></label>
                                    <textarea id="notes" maxlength="500" class="form-control" rows="3"></textarea>
								</div> -->
								<div id="appointment-details-2"></div>
							</div>

							<?php if ($display_terms_and_conditions) : ?>
								<label>
									<input type="checkbox" class="required" id="accept-to-terms-and-conditions">
									<?= strtr(
										lang('read_and_agree_to_terms_and_conditions'),
										[
											'{$link}' => '<a href="#" data-toggle="modal" data-target="#terms-and-conditions-modal">',
											'{/$link}' => '</a>'
										]
									)
									?>
								</label>
								<br>
							<?php endif ?>

							<?php if ($display_privacy_policy) : ?>
								<label>
									<input type="checkbox" class="required" id="accept-to-privacy-policy">
									<?= strtr(
										lang('read_and_agree_to_privacy_policy'),
										[
											'{$link}' => '<a href="#" data-toggle="modal" data-target="#privacy-policy-modal">',
											'{/$link}' => '</a>'
										]
									)
									?>
								</label>
								<br>
							<?php endif ?>

						</div>
						<?php if ($this->settings_model->get_setting('require_captcha') === '1') : ?>
							<div class="frame-content row">
								<div class="col-xs-12 col-sm-6">
									<h4 class="captcha-title">
										CAPTCHA
										<small class="glyphicon glyphicon-refresh"></small>
									</h4>
									<img class="captcha-image" src="<?= site_url('captcha') ?>">
									<input class="captcha-text" type="text" value="" />
									<span id="captcha-hint" class="help-block" style="opacity:0">&nbsp;</span>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<div style="display: inline-block;">
						<!--Spacer-->
					</div>

					<div class="command-buttons">
						<button type="button" id="button-back-3" class="btn button-back btn-default" data-step_index="3">
							<span class="glyphicon glyphicon-backward"></span>
							<?= lang('back') ?>
						</button>
						<form id="book-appointment-form" style="display:inline-block" method="post">
							<button id="book-appointment-submit" type="button" class="btn btn-success button-next" data-step_index="3">
								<span class="glyphicon glyphicon-ok"></span>
								<?= !$manage_mode ? lang('confirm') : lang('update') ?>
							</button>
							<input type="hidden" name="csrfToken" />
							<input type="hidden" name="post_data" />
						</form>
					</div>
				</div>

				<!-- FRAME FOOTER -->

				<div id="frame-footer">
					<a href="https://github.com/tekhnee/appointments/" target="_blank" style="color: #666666; font-size: 90%;">TekhneeAppointments</a>
					|
					<span id="select-language" class="label label-success">
						<?= $this->config->item('available_languages')[$this->config->item('language')][2] ?>
					</span>
					<a href="<?= site_url('backend'); ?>" style="color: #666666; font-size: 90%;">
						<?= $this->session->user_id ? ' ' : ' ' ?>
					</a>
					<div class="attributions-footer">
						<a href="https://github.com/tekhnee/appointments">TekhneeAppointments</a>&mdash;a fork of <a href="http://easyappointments.org/">Easy!Appointments</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php if ($display_cookie_notice === '1') : ?>
		<?php require 'cookie_notice_modal.php' ?>
	<?php endif ?>

	<?php if ($display_terms_and_conditions === '1') : ?>
		<?php require 'terms_and_conditions_modal.php' ?>
	<?php endif ?>

	<?php if ($display_privacy_policy === '1') : ?>
		<?php require 'privacy_policy_modal.php' ?>
	<?php endif ?>

	<script>
		var GlobalVariables = {
			availableServices: <?= json_encode($available_services) ?>,
			availableProviders: <?= json_encode($available_providers) ?>,
			baseUrl: <?= json_encode(config('base_url')) ?>,
			manageMode: <?= $manage_mode ? 'true' : 'false' ?>,
			customerToken: <?= json_encode($customer_token) ?>,
			dateFormat: <?= json_encode($date_format) ?>,
			timeFormat: <?= json_encode($time_format) ?>,
			displayCookieNotice: <?= json_encode($display_cookie_notice === '1') ?>,
			appointmentData: <?= json_encode($appointment_data) ?>,
			providerData: <?= json_encode($provider_data) ?>,
			customerData: <?= json_encode($customer_data) ?>,
			displayAnyProvider: <?= json_encode($display_any_provider) ?>,
			csrfToken: <?= json_encode($this->security->get_csrf_hash()) ?>,
			timezone: '',
			isActiveDate: Array(31).fill(false),
			availableLanguages: <?= json_encode($this->config->item('available_languages')) ?>,
			language: <?= json_encode($this->config->item('language')) ?>,
			server_timezone: 'UTC'
		};
		var EALang = <?= json_encode($this->lang->language) ?>;
	</script>

	<script src="<?= asset_url('assets/js/general_functions.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/datejs/date.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/moment/moment-with-locales.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/moment/moment-timezone-with-data-10-year-range.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/jquery/jquery.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/jquery-ui/jquery-ui.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/jquery-qtip/jquery.qtip.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/jquery-loading-overlay/jquery-loading-overlay.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/bootstrap/js/bootstrap.min.js') ?>"></script>
	<script src="<?= base_url('assets/js/bootstrap_whitelist.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/cookieconsent/cookieconsent.min.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/select2/select2.min.js') ?>"></script>
	<script src="<?= asset_url("assets/ext/select2/i18n/{$this->config->item('available_languages')[$this->config->item('language')][5]}.js") ?>"></script>
	<script src="<?= asset_url('assets/js/frontend_book_api.js') ?>"></script>
	<script src="<?= asset_url('assets/js/maximizeSelect2Height.min.js') ?>"></script>
	<script src="<?= asset_url('assets/js/frontend_book.js') ?>"></script>
	<script src="<?= asset_url('assets/js/timezone_switch.js') ?>"></script>
	<script src="<?= asset_url('assets/ext/cldrjs-dist/cldr.js') ?>"></script>

	<script>
		$(document).ready(function() {
			FrontendBook.initialize(true, GlobalVariables.manageMode);
			GeneralFunctions.enableLanguageSelection($('#select-language'));
		});
	</script>

	<?php google_analytics_script(); ?>
</body>

</html>