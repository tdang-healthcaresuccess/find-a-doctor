jQuery(function($) {
    // Highlight row on hover
    $('.wp-list-table tr').hover(function() {
        $(this).addClass('highlight');
    }, function() {
        $(this).removeClass('highlight');
    });
});

jQuery(document).ready(function ($) {
    $('#import-doctor-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'fnd_handle_doctor_upload'); 

        $('#fnd-import-overlay').show();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                $('#fnd-import-overlay').hide();
                $('#fnd-import-result').html(response); 
            },
            error: function () {
                $('#fnd-import-overlay').hide();
                $('#fnd-import-result').html('<div class="error"><p>Something went wrong. Please try again.</p></div>');
            }
        });
    });
});

function fndToggleGroup(group, check) {
  var root = document.querySelector('.fnd-checkbox-grid[data-group="' + group + '"]');
  if (!root) return;
  root.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
    cb.checked = !!check;
  });
}

document.getElementById('profile_img')?.addEventListener('change', function (e) {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  const img = document.getElementById('fnd-profile-preview');
  if (!img) return;
  const reader = new FileReader();
  reader.onload = function (ev) { img.src = ev.target.result; };
  reader.readAsDataURL(file);
});




