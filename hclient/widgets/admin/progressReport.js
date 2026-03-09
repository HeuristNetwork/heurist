/**
* @file progressReport.js
* @brief progressReport widget - shows progress report for long operations.
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7
*/

/**
* @namespace Widgets.Admin
* @description Admin and Db widgets
*/

/**
* @class progressReport
* @memberof Widgets.Admin
* @description shows progress report for long operations.
*
* @property {object} options - Configuration options for the widget.
*/
$.widget('heurist.progressReport', {
    options: {
        // data
        session_id: null,

        endpoint: null,

        interval: 900,

        // UI
        steps: null,
        content: null,
        showDialog: true,
        dialog: { width: 500, hideTitle: true },

        // hooks
        onAbort: null,         // function(api)
        onComplete: null,      // function(result)
        onError: null          // function(err)
    },

    _create: function(){
        this._intervalId = null;
        this._elapsed = 0;

        this._buildUI();
        this._bindUI();
    },

    _buildUI: function(){
        const o = this.options;

        if (!o.session_id) {
            o.endpoint = window.hWin.HAPI4.baseURL + 'hserv/controller/progress.php';
        }

        if (!o.session_id) {
            o.session_id = window.hWin.HEURIST4.util.random(); //String(Date.now()) + "_" + Math.floor(Math.random()*1e6);
        }

        // content
        let content = o.content;

        if (!content) {
            content = '';
            if (Array.isArray(o.steps)) {
                content += '<ol type="1" style="font-size:12px;height:80%;padding-top:20px;" class="progress-steps">';
                o.steps.forEach((s)=>{ content += `<li style="color:gray">${s}</li>`; });
                        content += '</ol>';
            } else {
                content += '<div class="loading" style="height:20%;min-height:50px"></div>';
            }
            content += '<div class="progress-bottom" style="display:none;width:80%;height:40px;padding:5px;text-align:center;margin:auto;margin-top:10px">'
            + '<div class="progressbar"><div class="progress-label">Processing data.</div></div>'
            + '<div class="progress_stop" style="text-align:center;margin-top:4px;cursor:pointer">Abort</div>'
            + '</div>';
        }

        this.element.empty().append(content);

        // cache refs
        this.$steps = this.element.find('.progress-steps');
        this.$bottom = this.element.find('.progress-bottom');
        this.$bar = this.element.find('.progressbar');
        this.$label = this.element.find('.progress-label');
        this.$abort = this.element.find('.progress_stop').button();

        // init progressbar
        if (this.$bar.length) {
            this.$bar.progressbar({ value: 0 });
        }

        // dialog mode
        if (o.showDialog) {
            const dlgOpts = $.extend({
                modal: true,
                resizable: false,
                width: o.dialog.width || 500,
                height: o.dialog.height,
                closeOnEscape: false
                }, (o.dialog||{}));

            // leverage existing msg.showMsgDlg if you prefer, but plain dialog is fine
            this.$dialog = this.element.dialog(dlgOpts);

            if (o.dialog.hideTitle) {
                this.$dialog.parent().find('.ui-dialog-titlebar').hide();
            }
        }else{
            this.element.show();
        }
    },

    _bindUI: function(){
        this._on(this.$abort, {
            click: function(){
                const o = this.options;
                if ($.isFunction(o.onAbort)) {
                    o.onAbort(this);
                } else if (o.endpoint) {
                    
                    let request = {terminate:1, t:(new Date()).getMilliseconds(), session:o.session_id};
                    window.hWin.HEURIST4.util.sendRequest(o.endpoint, request, null, (response)=>{
                        this.destroy();
                    });
                }
            }
        });
    },

    getSessionId: function(){
        return this.options.session_id;
    },

    start: function(){
        if (this._intervalId) return;
        $('body').css('cursor','progress');

        this._poll(true);
        this._intervalId = setInterval(()=>this._poll(false), this.options.interval);

        return this.options.session_id;
    },

    stop: function(){
        $('body').css('cursor','auto');
        if (this._intervalId) {
            clearInterval(this._intervalId);
            this._intervalId = null;
        }
    },

    destroy: function(){
        this.stop();
        if (this.$dialog && this.$dialog.dialog('instance')) {
            this.$dialog.dialog('close');
        }else{
            this.element.hide();
        }
        this._super();
    },

    _poll: function(isFirst){
        const o = this.options;

        const t_interval = o.interval;

        //$.post(o.endpoint, { t:Date.now(), session: o.session_id }, (txt)=>{
            
        window.hWin.HEURIST4.util.sendRequest(o.endpoint, { t:Date.now(),session: o.session_id }, null, (txt)=>{   

            //console.log('>>',txt);                
                 
                if(txt?.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    this.stop();
                    return;
                }
            
                // expected formats per your existing code:
                // - "terminate"
                // - "done,total" or "done,total,note"
                // - step updates may be embedded (your current logic handles)
                if (!txt) return;

                // termination
                if (String(txt).trim() === 'terminate') {
                    this._complete({status:'terminated'});
                    return;
                }

                // parse "done,total[,note]"
                const resp = String(txt).split(',');
                let done = parseInt(resp[0], 10) || 0;
                let total = parseInt(resp[1], 10) || 0;
                let note = (resp.length>=3 ? resp.slice(2).join(',') : '');

                this.setProgress(done, total, note);
        },'text');

        //    }, 'txt').fail((xhr)=>{
        //        if ($.isFunction(o.onError)) o.onError(xhr);
        //});

        this._elapsed += t_interval;
    },

    setProgress: function(done, total, note){
        if (!this.$bar.length) return;

        if (done>0 && total>0) {
            this.$bottom.show();

            const val = done*100/total;
            this.$bar.progressbar('value', val);

            // ETA like your existing code :contentReference[oaicite:2]{index=2}
            let est = (this._elapsed / done) * (total - done);
            let estTxt = '10 seconds';
            if (est >= 60000) estTxt = `${Math.ceil(est/60000)} minutes`;
            else if (est >= 10000) estTxt = `${Math.ceil(est/1000)} seconds`;

            this.$label.text(`${done} of ${total} ${(note||'')} (approximately ${estTxt} remaining)`);
        } else {
            this.$label.text('preparing...');
            this.$bar.progressbar('value', 0);
        }
    },

    _complete: function(result){
        this.stop();
        if ($.isFunction(this.options.onComplete)) {
            this.options.onComplete(result);
        }
        // default close
        if (this.$dialog && this.$dialog.dialog('instance')) {
            this.$dialog.dialog('close');
        } else {
            this.element.hide();
        }
    }
});
