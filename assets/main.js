jQuery(function ($) {
    $(document).ready(function () {

        $('[data-button="upload"]').on('click', function (e) {
            e.preventDefault();

            var fileInput = $('.form-file')[0];
            var file = fileInput.files[0];

            if (file) {
                var formData = new FormData();
                formData.append('file', file);
                $.ajax({
                    url: 'upload/index.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function () {
                        $('.progress-bar').css('width', '0%');
                        $('.progress-bar').attr('aria-valuenow', 0);
                    },
                    xhr: function () {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = evt.loaded / evt.total * 100;
                                $('.progress').show();
                                $('.progress-bar').css('width', percentComplete + '%');
                                $('.progress-bar').attr('aria-valuenow', percentComplete);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function (response) {
                        if (response.error) {
                            alert(response.error);
                        } else {
                            alert(response);
                        }

                        $('.progress-bar').removeClass('bg-danger');
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        alert('Error uploading file:', textStatus, errorThrown);
                        $('.progress-bar').addClass('bg-danger');
                    }
                });
            } else {
                alert("No file selected.");
            }
        })

    })
})