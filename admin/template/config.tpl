{combine_css path=$CORE_PRIVACY_TOGGLE_PATH|@cat:"admin/template/style.css"}

{footer_script}
jQuery('input[name="option2"]').change(function() {
  $('.option1').toggle();
});

jQuery(".showInfo").tipTip({
  delay: 0,
  fadeIn: 200,
  fadeOut: 200,
  maxWidth: '300px',
  defaultPosition: 'bottom'
});
{/footer_script}


<div class="titrePage">
	<h2>Core Privacy Toggle</h2>
</div>

<form method="post" action="" class="properties">
<fieldset>
  <legend>{'Common configuration'|translate}</legend>

  <ul>
    <li>
      <label>
        <input type="checkbox" name="option2" value="1" {if $core_privacy_toggle.option2}checked="checked"{/if}>
        <b>{'Checkbox'|translate}</b>
      </label>
      <a class="icon-info-circled-1 showInfo" title="{'Check me!'|translate}"></a>
    </li>
    <li class="option1" {if not $core_privacy_toggle.option2}style="display:none;"{/if}>
      <label>
        <b>{'Integer'|translate}</b>
        <input type="text" name="option1" value="{$core_privacy_toggle.option1}" size="4">
      </label>
    </li>
    <li>
      <label>
        <b>{'Select'|translate}</b>
        {html_options name=option3 options=$select_options selected=$core_privacy_toggle.option3}
      </label>
    </li>
  </ul>
</fieldset>

<p class="formButtons"><input type="submit" name="save_config" value="{'Save Settings'|translate}"></p>

</form>