{{#if nameValue}}
  {{#if idValue}}
    <a href="#{{foreignScope}}/view/{{idValue}}" {{#if isCustom}} style="font-style: italic;" {{/if}} title="{{titleValue}}">{{nameValue}}</a>
  {{else}}
    {{nameValue}}
  {{/if}}
{{/if}}

