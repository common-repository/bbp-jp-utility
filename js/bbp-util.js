
window.addEventListener('DOMContentLoaded', function(){    

    WPCustomRegister = function(nonce){
        window.bbpajax = {};
        window.bbpajax['params'] = { action: "custom_register", _ajax_nonce: nonce,};
        window.bbpajax['selector'] = "form.bbp-login-form.bbp-register input";
        window.bbpajax['success'] = { code:'register', msg: '.custom-register-info', action: null };
        window.bbpajax['error'] = { code: 'e_register', msg: '.custom-register-info', action: null };

        if(typeof renderInvisibleReCaptcha !== "undefined"){
            grecaptcha.execute();
        } else {
            BBPAjax_submit( BBPAjax_extparm() );
        }
        return false; 
    };

    WPCustomLogin = function(nonce, is_widget, redirect, lostpass){
        window.bbpajax = {};
        window.bbpajax['params'] = { action: "custom_login", _ajax_nonce: nonce, redirect_to:redirect, lostpass:lostpass,};
        window.bbpajax['selector'] = (is_widget)? ".bbp_widget_login > form.bbp-login-form input" : ":not(.bbp_widget_login) > form.bbp-login-form input";
        window.bbpajax['success'] = { code:'login_redirect', msg: null, action: 'redirect' };
        window.bbpajax['error'] = { code: 'e_login', msg: '.custom-login-info', action: null };

        if(typeof renderInvisibleReCaptcha !== "undefined"){
            grecaptcha.execute();
        } else {
            BBPAjax_submit( BBPAjax_extparm() );
        }
        return false; 
    };

    WPCustomResetPass = function(nonce){
        window.bbpajax = {};
        window.bbpajax['params'] = { action: "custom_resetpass", _ajax_nonce: nonce,};
        window.bbpajax['selector'] = "form.bbp-login-form.bbp-lost-pass input";
        window.bbpajax['success'] = { code:'resetpass', msg: '.custom-resetpass-info', action: null };
        window.bbpajax['error'] = { code: 'e_resetpass', msg: '.custom-resetpass-info', action: null };

        if(typeof renderInvisibleReCaptcha !== "undefined"){
            grecaptcha.execute();
        } else {
            BBPAjax_submit( BBPAjax_extparm() );
        }
        return false; 
    };

    BBPAjax_extparm = function() {
        const params = window.bbpajax['params'];
        const selector = window.bbpajax['selector'];
        const items = document.querySelectorAll( selector );
        for (let i = 0; i < items.length; i++) {
            if(items[i].name && items[i].name != 'g-recaptcha-response'){
                if(items[i].type == 'checkbox'){
                    params[ items[i].name ] = (items[i].checked != false)? 1 : 0;
                } else {
                    params[ items[i].name ] = items[i].value;
                }
            }
        }
        const recaptcha = document.querySelectorAll( "*[name='g-recaptcha-response']" );
        for (let i = 0; i < recaptcha.length; i++) {
            if(recaptcha[i].textLength > 0){
                params[ recaptcha[i].name ] = recaptcha[i].value;        
                break;
            }
        }
        return params;
    }
    
    BBPAjax_submit = function( params ) {
        document.querySelectorAll(window.bbpajax['error'].msg).forEach((function(el){ el.innerHTML = '';}));                
        fetch( bbputil['ajax-url'], {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()  
        })
        .then(function(response) {
            if(response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok.');     
        })    
        .then(function(json) {
            if (typeof renderInvisibleReCaptcha !== "undefined") {
                grecaptcha.reset();
            }
            let result = window.bbpajax['success'];
            if (json.data.result !== result.code) {
                result = window.bbpajax['error'];
            }
            if (result.msg != null) {
                document.querySelectorAll(result.msg).forEach((function(el){ el.innerHTML = json.data.info;}));                
            }
            if (result.action == 'redirect') {
                window.location.href = json.data.info;
            }
        })
        .catch(function(error) {
            if (typeof renderInvisibleReCaptcha !== "undefined") {
                grecaptcha.reset();
            }
            //alert("ajax response error");
        });    
    }

    bbpUnsubscribeDialog = function(nonce, user_id){
        window.bbpajax = {};
        window.bbpajax['params'] = { action: "cp_bbp_unsubscribe", user_id: user_id, _ajax_nonce: nonce,};
        document.getElementById("bbp-unsubscribe-dialog").style.display = "block";
    }
    bbpUnsubscribeDialogClose = function(){
        document.getElementById("bbp-unsubscribe-dialog").style.display = "none";
    }    
    bbpUnsubscribe = function(){
        const params = window.bbpajax['params'];
        fetch( bbputil['ajax-url'], {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()  
        })
        .then(function(response) {
            if(response.ok) {
                return response.json();
            }
            throw new Error('Network response was not ok.');     
        })    
        .then(function(json) {        
            bbpUnsubscribeDialogClose();
            alert( json.data );
            location.reload();
        })
        .catch(function(error) {
            bbpUnsubscribeDialogClose();
            //alert("ajax response error");            
        });    
    }    
});
