{{#if nameValue}}
  {{#if idValue}}
    <a href="#{{foreignScope}}/view/{{idValue}}" title="{{titleValue}}">{{nameValue}}</a>
  {{else}}
    {{nameValue}}
  {{/if}}
{{/if}}

