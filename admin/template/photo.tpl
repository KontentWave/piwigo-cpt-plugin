{combine_css path=$CORE_PRIVACY_TOGGLE_PATH|@cat:"admin/template/style.css"}

<h2>{$TITLE} &#8250; {'Edit photo'|translate} {$TABSHEET_TITLE}</h2>


<form action="{$F_ACTION}" method="post" id="catModify">
  <fieldset>
    <legend>{'My awesome photo tab'|translate}</legend>

    <p>
      <img src="{$TN_SRC}" alt="{'Thumbnail'|translate}" class="Thumbnail">
    </p>

    <p>
      <input class="submit" type="submit" value="{'Save'|translate}" name="save_core_privacy_toggle">
    </p>
  </fieldset>
</form>