<?php
namespace PommePause\Dropinambour;

// Views variables
// End of Views variables

$this->layout('/page', ['title' => "Login | dropinambour - Requests for Plex"]);
?>

<h1>dropinambour - Requests for Plex</h1>

<a id="login_button" class="btn btn-primary mt-4" href="<?php phe(Plex::getAuthURL()) ?>" target="plex_auth" onclick="open_plex_login(this); return false">Login with Plex</a>

<div class="alert alert-success d-none">Login successful. Reloading...</div>

<script>
    var auth_window;
    function open_plex_login(button) {
        let $btn = $(button);
        $btn.text('Waiting for Plex login...').prop('disabled', true).addClass('disabled');
        auth_window = window.open($btn.prop('href'));
        console.log(auth_window);
        setTimeout(wait_until_login_succeeded, 4000);
    }
    function wait_until_login_succeeded() {
        console.log("Checking if Plex login succeeded...");
        $.ajax({
            method: 'GET',
            url: <?php echo json_encode(Router::getURL(Router::ACTION_AJAX, Router::AJAX_CHECK_LOGIN)) ?>,
        }).done(function (data) {
            console.log(data);
            if (data.login_success) {
                auth_window.close()
                $('.alert.alert-success').removeClass('d-none');
                $('#login_button').detach();
                window.location.href = './';
            } else {
                setTimeout(wait_until_login_succeeded, 4000);
            }
        });
    }
</script>
