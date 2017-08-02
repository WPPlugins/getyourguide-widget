<select name="<?php echo self::OPTION_NAME_LOCALE; ?>" >
	<?php foreach ($values as $localeCode => $localeName): ?>
		<option value="<?php echo $localeCode ?>" <?php echo (($localeCode == $locale) ? selected(true) : ''); ?>>
			<?php echo esc_html($localeName) ?>
		</option>
	<?php endforeach; ?>
</select>
<p class="description">
	<?= _e('Select the Language for the widget.', 'getyourguide-widget'); ?>
</p>