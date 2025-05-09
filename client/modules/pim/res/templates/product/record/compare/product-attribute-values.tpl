<div class="list">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered" data-name="{{name}}">
        <colgroup>
            {{#each columns}}
            {{#if isFirst }}
            <col style="width: 250px;">
            {{else}}
            {{#if ../merging}}
            <col style="width: 50px">
            {{/if}}
            <col class="col-min-width">
            {{/if}}
            {{/each}}
        </colgroup>
        <thead>
        <tr>
            {{#each columns}}
                {{#unless isFirst }}
                    {{#if ../merging }}
                        <th></th>
                    {{/if}}
                {{/unless}}
            <th class="text-center">
                {{{name}}}
                {{#if _error}}
                <br>
                <span class="danger"> ({{_error}})</span>
                {{/if}}
            </th>
            {{/each}}
        </tr>
        </thead>
        <tbody>
        {{#if noData }}
        <tr>
            <td colspan="{{columnLength}}"> {{ translate 'No Data' }} </td>
        </tr>
        {{else}}
        {{#each attributeList}}
        <tr>
            <td colspan="{{../columnLength}}"><h5>{{label}}</h5></td>
        </tr>
            {{#each attributes }}
            <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{key}}">
                <td  title="{{label}}">
                    <div>
                        <a href="{{# if instanceUrl}} {{instanceUrl}}/{{/if}}#Attribute/view/{{attributeId}}" {{# if instanceUrl}}target="_blank"{{/if}}> {{{label}}}</a>
                    </div>
                </td>
                {{#if ../../merging}}
                <td>
                    <div class="center-child" >
                        <input type="radio" name="{{key}}" value="{{modelId}}" data-id="{{modelId}}" disabled="disabled" class="field-radio">
                    </div>
                </td>
                {{/if}}
                <td class="{{#unless shouldNotCenter}} text-center{{/unless}}">
                  <div class="field">
                      <div class="current">Loading...</div>
                  </div>
                </td>
                {{#each others}}
                    {{#if ../../../merging}}
                        <td>
                            <div class="center-child" >
                                <input type="radio" name="{{../key}}" value="{{modelId}}" data-id="{{modelId}}"  data-attribute_id="{{../attributeId}}" data-channel_id="{{../channelId}}"  data-language="{{../language}}"  class="field-radio">
                            </div>
                        </td>
                    {{/if}}
                    <td class="{{#unless shouldNotCenter}} text-center{{/unless}}">
                       <div class="field">
                           <div class="other{{index}}">Loading...</div>
                       </div>
                    </td>
                {{/each}}
            </tr>
            {{/each}}
        {{/each}}
        {{/if}}
        </tbody>
    </table>
</div>
