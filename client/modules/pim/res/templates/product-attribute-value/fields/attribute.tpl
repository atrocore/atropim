{{#if nameValue}}
  {{#if idValue}}
    <a href="#{{foreignScope}}/view/{{idValue}}" title="{{titleValue}}" data-tooltip="{{idValue}}">{{nameValue}}</a>
  {{else}}
    {{nameValue}}
  {{/if}}
{{/if}}

