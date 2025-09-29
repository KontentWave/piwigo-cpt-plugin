{combine_css path=$CORE_PRIVACY_TOGGLE_PATH|@cat:"admin/template/style.css"}

<div class="titrePage">
  <h2>Core Privacy Toggle</h2>
</div>

<div class="properties cpt-admin">
  <fieldset>
    <legend>{'Status'|translate}</legend>
    <p><strong>{$CPT_STATUS|escape}</strong></p>
    <p><em>{$CPT_PHASE_INFO|escape}</em></p>
    
    <h4>{'How it works'|translate}</h4>
    <ul>
      <li>{'Users can manage their albums from their profile page'|translate}</li>
      <li>{'Album name, description and privacy can be edited'|translate}</li>
      <li>{'Works with Community plugin ownership or fallback detection'|translate}</li>
    </ul>
  </fieldset>
</div>