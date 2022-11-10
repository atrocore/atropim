{{#if groups.length}}
<div class="group-container">
    {{#each groups}}
    <div class="group" data-name="{{key}}">
        <div class="list-container">&nbsp;{{translate 'Loading...'}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<div class="list-container">{{translate 'No Data'}}</div>
{{/if}}
