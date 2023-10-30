if ((typeof ACF_AJAX !== 'function')) {

    window.ACF_AJAX = class {

        static default_options = {
            form_selector:   '.acf-form',
            invalid_message: 'Please fix invalid entries and resubmit the form',
            success_message: 'Submission Successful!',
        };
    
        $form = null;
        $messages = null;
    
        constructor (options) {
    
            this.options = Object.assign({}, ACF_AJAX.default_options, options || {});
    
            this.$form = jQuery(this.options.form_selector);
    
            if (!this.$form.length) {
    
                console.error(`ACF form '${this.options.form_selector}' could not be found.`);
                return;
    
            }
    
            this.$messages = jQuery(`<div class='acf-ajax-messages'></div>`).prependTo(this.options.form_selector + ' .acf-form-submit');
    
            this.add_event_listeners();
    
        }
    
        add_event_listeners () {
    
            this.$form.on('submit', function(e){
                
                e.preventDefault();
            
            });
    
            this.$form.on('click','.acf-button', function() {
    
                this.validate_form();
    
            }.bind(this));
    
        }
    
        validate_form () {
    
            this.reset();
    
            acf.validateForm({
    
                form:     this.$form,
                reset:    true,
                success:  ($form) => this.submit_form(),
                failure:  () => this.message(this.options.invalid_message, 'error'),
                loading:  () => {},
                complete: () => {},
    
            });
    
        }
    
        submit_form () {
    
            let $file_inputs = jQuery('input[type="file"]:not([disabled])', this.$form) // Fix for Safari Webkit – empty file inputs kill the browser https://stackoverflow.com/a/49827426/586823
            $file_inputs.each(function(i, input) {
                if(input.files.length > 0) return;
                jQuery(input).prop('disabled', true);
            });
    
            var formData = new FormData(this.$form[0]);
            
            $file_inputs.prop('disabled', false); // Re-enable empty file $file_inputs
    
            acf.lockForm(this.$form);
    
            jQuery.ajax({
    
                url:         window.location.href,
                method:      'post',
                data:        formData,
                cache:       false,
                processData: false,
                contentType: false
    
            }).done(response => {
    
                acf.unlockForm(this.$form);
                this.success(response);
    
            });
    
        }
    
        success (response) {
    
            // console.log(response);
    
            this.message(this.options.success_message, 'success');
    
        }
    
        reset () {
    
            this.$messages.empty();
    
        }
    
        message (message, type = 'info') {
    
            jQuery(`<div class='acf-ajax-message acf-notice -${type}'>${message}</div>`).appendTo(this.$messages);
    
        }
    
    }
    
    

}