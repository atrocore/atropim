{{#if hasUrl}}
<a href="{{url}}"><span {{#if backgroundColor}}class="label" style='background-color: #{{backgroundColor}}; color: #{{color}}'{{/if}}>{{label}}</span></a>
{{else}}
<span {{#if backgroundColor}}class="label" style='background-color: #{{backgroundColor}}; color: #{{color}}'{{/if}}>{{label}}</span>
{{/if}}