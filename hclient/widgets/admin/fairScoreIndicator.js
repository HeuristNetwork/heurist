/**
* @file fairScoreIndicator.js
* @brief Small top-bar badge showing the database's FAIR score, with an info popup, plus a
*        once-a-month "FAIRness estimate" popup with improvement suggestions.
* @fileOverview Reads window.hWin.HAPI4.sysinfo.fair_score (populated server-side by
*        System::getFairScoreSummary(), which in turn reads FAIRscore.txt as calculated nightly
*        by admin/describe/assessFAIR.php). Purely a display layer - no calculation happens here.
*
* INTEGRATION NOTE for Ian/Nathan: this is delivered as a self-contained widget with an explicit
* mount point (heurist.fairScoreIndicator) rather than being wired directly into the shared topbar
* template, since that markup is assembled dynamically and editing it blind risked breaking layout.
* To surface it, call:
*     $('#some-topbar-container').fairScoreIndicator();
* from wherever the top bar is finalised (e.g. alongside the existing sysadmin/help icons).
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7
*/

$.widget("heurist.fairScoreIndicator", {

    options: {
        // re-show the monthly popup automatically if it's been this many days since last shown
        popupIntervalDays: 30
    },

    _create: function(){
        this._renderBadge();
        this._maybeShowMonthlyPopup();
    },

    _getScore: function(){
        const score = window.hWin.HAPI4.sysinfo && window.hWin.HAPI4.sysinfo['fair_score'];
        return score || {F:1, A:0, I:1, R:1, TOTAL:3, is_default:true, has_synced_metadata:false, suggestions:[]};
    },

    _colourForScore: function(total){
        if(total>=8) return '#2e7d32';      // green
        if(total>=6) return '#9e9d24';      // olive
        if(total>=4) return '#ef6c00';      // orange
        return '#c62828';                   // red
    },

    _renderBadge: function(){

        const score = this._getScore();
        const total = Math.round(score.TOTAL || 0);
        const colour = this._colourForScore(total);

        this.element.empty();

        if(score.is_default){ // skip badge rendering
            return;
        }

        let $badge = $('<span>', {
            'class': 'fair-score-badge',
            title: window.hWin.HR('Click to see information about FAIR principles and this rating')
        }).css({
            display: 'inline-block',
            padding: '2px 8px',
            'border-radius': '10px',
            'font-size': '0.85em',
            'font-weight': 'bold',
            color: '#fff',
            'background-color': colour,
            cursor: 'pointer'
        }).text(`FAIR ${total}/10 (experiment)`);

        this._on($badge, {click: this._showInfoPopup});

        this.element.append($badge);
    },

    /**
     * Rollover/click info popup explaining the rating - "Click to see information about FAIR
     * principles and this rating" per Ian's spec.
     */
    _showInfoPopup: function(){

        const score = this._getScore();
        const total = Math.round((score.TOTAL || 0) * 10) / 10;

        let html = '<div style="max-width:420px;">'
            + '<p>' + window.hWin.HR('We calculate a (very approximate) rating of how well your database '
                + 'corresponds with FAIR principles (Findable, Accessible, Interoperable and Reusable).') + '</p>'
            + '<table style="width:100%;border-collapse:collapse;margin:0.5em 0;">'
            + this._scoreRow('Findable', score.F)
            + this._scoreRow('Accessible', score.A)
            + this._scoreRow('Interoperable', score.I)
            + this._scoreRow('Reusable', score.R)
            + '<tr><td style="font-weight:bold;padding-top:0.5em;">' + window.hWin.HR('Total') + '</td>'
            + '<td style="font-weight:bold;padding-top:0.5em;text-align:right;">' + total + ' / 10</td></tr>'
            + '</table>'
            + '<p><a href="https://www.go-fair.org/fair-principles/" target="_blank">'
            + window.hWin.HR('Read more about FAIR principles') + '</a></p>';

        if(window.hWin.HEURIST4.util.isPositiveInt(window.hWin.HAPI4.sysinfo['db_registeredid'])){
            const editURL = window.hWin.HAPI4.sysinfo['referenceServerURL']
                + '?fmt=edit&recID=' + window.hWin.HAPI4.sysinfo['db_registeredid']
                + '&db=' + window.hWin.HAPI4.sysinfo.referenceServerIndexDatabase;
            html += '<p><a href="' + editURL + '" target="_blank">'
                + window.hWin.HR('Edit your database metadata on the Heurist Reference Index') + '</a></p>';
        }

        html += '</div>';

        window.hWin.HEURIST4.msg.showMsgDlg(html, null, {title: 'FAIR score'},
            {default_palette_class: 'ui-heurist-admin', dialogId: 'fair-score-info'});
    },

    _scoreRow: function(label, value){
        value = (typeof value === 'number') ? value : 0;
        return '<tr><td>' + window.hWin.HR(label) + '</td>'
            + '<td style="text-align:right;">' + value + ' / 2.5</td></tr>';
    },

    /**
     * Shows the monthly "FAIRness estimate" popup (per Artem's spec) - only the suggestions that
     * are actually relevant are shown (e.g. omit the visibility suggestion if data is already public).
     * Uses HAPI4 user preferences to track when it was last shown, so it surfaces roughly monthly.
     */
    _maybeShowMonthlyPopup: function(){

        return; // block monthly popup, doesn't account for other popups that maybe open (e.g. new version or monthly ticket request)

        const score = this._getScore();
        if(score.is_default){
            return; // no real assessment yet (not registered / not yet scored) - nothing useful to show
        }

        const prefKey = 'fairness_popup_last_shown';
        const lastShown = window.hWin.HAPI4.get_prefs ? window.hWin.HAPI4.get_prefs(prefKey) : null;
        const now = Date.now();
        const intervalMs = this.options.popupIntervalDays * 24 * 60 * 60 * 1000;

        if(lastShown && (now - Number(lastShown)) < intervalMs){
            return;
        }

        let that = this;

        // give the page a moment to settle before popping this up
        setTimeout(function(){ that._showFairnessPopup(); }, 1500);

        if(window.hWin.HAPI4.save_pref){
            window.hWin.HAPI4.save_pref(prefKey, String(now));
        }
    },

    _showFairnessPopup: function(){

        const score = this._getScore();
        const total = Math.round((score.TOTAL || 0) * 10) / 10;
        const suggestions = score.suggestions || [];

        const suggestionText = {
            visibility: window.hWin.HR('by making all or most of the data publicly visible'),
            website: window.hWin.HR('by creating a well structured interactive website'),
            website_quality: window.hWin.HR('by further developing your website (it currently shows little sign of configuration)'),
            metadata: window.hWin.HR('by creating better metadata describing your database'),
            doi: window.hWin.HR('by obtaining and recording a DOI for your database')
        };

        let items = '';
        suggestions.forEach(function(key){
            if(suggestionText[key]){
                items += '<li>' + suggestionText[key] + '</li>';
            }
        });

        let html = '<div style="max-width:480px;">'
            + '<p>' + window.hWin.HR('We calculate a (very approximate) rating of how well your database '
                + 'corresponds with FAIR principles (Findable, Accessible, Interoperable and Reusable). '
                + "Nearly half the score is automatic due to Heurist's structure and functions relating to "
                + 'findability, interoperability and re-use. The other half depends on the openness of your '
                + 'data (public visibility and website) and the quality of the metadata you have created to '
                + 'describe the database.') + '</p>'
            + '<p style="font-weight:bold;">' + window.hWin.HR('Our FAIRness rating for your database is')
            + ' ' + total + ' / 10</p>';

        if(items){
            html += '<p>' + window.hWin.HR('You can improve this rating:') + '</p><ul>' + items + '</ul>';
        }

        if(window.hWin.HEURIST4.util.isPositiveInt(window.hWin.HAPI4.sysinfo['db_registeredid'])){
            const editURL = window.hWin.HAPI4.sysinfo['referenceServerURL']
                + '?fmt=edit&recID=' + window.hWin.HAPI4.sysinfo['db_registeredid']
                + '&db=' + window.hWin.HAPI4.sysinfo.referenceServerIndexDatabase;
            html += '<p><a href="' + editURL + '" target="_blank">' + window.hWin.HR('Edit the metadata here') + '</a></p>';
        }

        html += '<p><a href="https://www.go-fair.org/fair-principles/" target="_blank">'
            + window.hWin.HR('Learn more about FAIR principles') + '</a></p>'
            + '</div>';

        window.hWin.HEURIST4.msg.showMsgDlg(html, null, {title: 'FAIRness estimate'},
            {default_palette_class: 'ui-heurist-admin', dialogId: 'fairness-monthly-popup', width: 520});
    },

    _destroy: function(){
        this.element.empty();
    }
});
