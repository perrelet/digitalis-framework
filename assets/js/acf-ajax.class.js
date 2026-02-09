if ((typeof ACF_AJAX !== 'function')) {

    window.ACF_AJAX = class {

        static default_options = {
            form_selector:    '.acf-form',
            invalid_message:  'Please fix invalid entries and resubmit the form',
            success_message:  'Submission Successful!',
            url:              window.location.href,
            return_data_type: 'html',
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
    
            this.$form.on('click', '.acf-form-submit input[type=submit]', function() {
    
                this.validate_form();
    
            }.bind(this));
    
        }
    
        validate_form () {
    
            this.reset();
            this.$form.addClass('validating loading');
    
            acf.validateForm({
    
                form:     this.$form,
                reset:    true,
                success:  ($form) => {

                    this.$form.removeClass('validating');
                    this.submit_form();

                },
                failure:  () => {

                    this.$form.removeClass('validating loading');
                    this.message(this.options.invalid_message, 'error')

                },
                loading:  () => {},
                complete: () => {},
    
            });
    
        }
    
        submit_form () {

            this.$form.addClass('submitting loading');
    
            let $file_inputs = jQuery('input[type="file"]:not([disabled])', this.$form) // Fix for Safari Webkit – empty file inputs kill the browser https://stackoverflow.com/a/49827426/586823
            $file_inputs.each(function(i, input) {
                if(input.files.length > 0) return;
                jQuery(input).prop('disabled', true);
            });
    
            var formData = new FormData(this.$form[0]);
            
            $file_inputs.prop('disabled', false); // Re-enable empty file $file_inputs
    
            acf.lockForm(this.$form);
    
            jQuery.ajax({
    
                url:         this.options.url,
                method:      'post',
                data:        formData,
                cache:       false,
                processData: false,
                contentType: false,
                dataType:    this.options.return_data_type
    
            }).done(response => {

                this.$form.removeClass('submitting loading');
                acf.unlockForm(this.$form);
                this.message(this.options.success_message, 'success');

                let args = {options: this.options, response: response};
                document.dispatchEvent(new CustomEvent('Digitalis/ACF_AJAX/Success', {detail: args}));
    
            }).fail((jqXHR, text_status, error) => {

                this.$form.removeClass('submitting loading');
                this.$form.addClass('error');
                this.message(error, 'error');

                let args = {xhr: jqXHR, text_status: text_status, error: error};
                document.dispatchEvent(new CustomEvent('Digitalis/ACF_AJAX/Error', {detail: args}));

            });
    
        }
    
        reset () {
    
            this.$messages.empty();
            this.$form.removeClass('validating submitting loading error');
    
        }
    
        message (message, type = 'info') {
    
            jQuery(`<div class='acf-ajax-message acf-notice -${type}'>${message}</div>`).appendTo(this.$messages);
    
        }
    
    }
    
    

}