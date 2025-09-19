{* <!-- load CSS files --> *}
{combine_css id="core_privacy_toggle" path=$CORE_PRIVACY_TOGGLE_PATH|cat:"template/style.css"}

{* <!-- load JS files --> *}
{* {combine_script id="core_privacy_toggle" require="jquery" path=$CORE_PRIVACY_TOGGLE_PATH|cat:"template/script.js"} *}

{* <!-- add inline JS --> *}
{footer_script require="jquery"}
  jQuery('#core_privacy_toggle').on('click', function(){
    alert('{'Hello world!'|translate}');
  });
{/footer_script}

{* <!-- add inline CSS --> *}
{html_style}
  #core_privacy_toggle {
    display:block;
  }
{/html_style}


{* <!-- add page content here --> *}
<h1>{'What Core Privacy Toggle can do for me?'|translate}</h1>

<blockquote>
  {$INTRO_CONTENT}
</blockquote>

<div id="core_privacy_toggle">{'Click for fun'|translate}</div>