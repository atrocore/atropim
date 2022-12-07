{{#if hasUrl}}
<a href="{{url}}"><span {{#if backgroundColor}}class="label" style='border: solid 1px #{{backgroundColor}}; background-color: #{{backgroundColor}}; color: #{{color}}'{{/if}}>{{label}}</span></a>
{{else}}
<span {{#if backgroundColor}}class="label" style='border: solid 1px #{{backgroundColor}}; background-color: #{{backgroundColor}}; color: #{{color}}'{{/if}}>{{label}}</span>
{{/if}}