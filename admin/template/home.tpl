{combine_css path=$CORE_PRIVACY_TOGGLE_PATH|@cat:"admin/template/style.css"}

{html_style}
  h4 {
    text-align:left !important;
  }
{/html_style}


<div class="titrePage">
	<h2>Core Privacy Toggle</h2>
</div>

<form method="post" action="" class="properties">
<fieldset>
  <legend>{'What Core Privacy Toggle can do for me?'|translate}</legend>

  {$INTRO_CONTENT}
</fieldset>

</form>