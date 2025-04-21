jQuery(document).ready(($) => {
    $("#easy-password-form").on("submit", (e) => {
        e.preventDefault();

        // Get email value
        const email = $("#user_email").val();

        // Show loading state
        $("#submit-password .button-text").addClass("hidden");
        $("#submit-password .spinner").addClass("active");

        // Clear previous results
        $("#password-text").text("");
        $(".password-message").text("");
        $("#easy-password-result").hide();

        $.ajax({
            url: easy_password_ajax.ajax_url,
            type: "POST",
            data: {
                action: "easy_password_process",
                email: email,
                nonce: easy_password_ajax.nonce,
            },
            success: (response) => {
                // Hide loading state
                $("#submit-password .button-text").removeClass("hidden");
                $("#submit-password .spinner").removeClass("active");

                if (response.success) {
                    // Extract password from the message
                    const message = response.data.message;
                    const passwordMatch = message.match(/Password: (.+)/);

                    if (passwordMatch?.[1]) {
                        const password = passwordMatch[1];
                        // Display password in the password box
                        $("#password-text").text(password);
                        // Display message below the password
                        $(".password-message").html("<p>Your password has been generated. Click the clipboard icon to copy.</p>");
                    } else {
                        // If pattern doesn't match, display the full message
                        $(".password-message").html(`<p>${message}</p>`);
                    }

                    $("#easy-password-result").fadeIn(300);
                } else {
                    $(".password-message").html(`<p class="error">Error: ${response.data}</p>`);
                    $("#easy-password-result").fadeIn(300);
                }
            },
            error: () => {
                // Hide loading state
                $("#submit-password .button-text").removeClass("hidden");
                $("#submit-password .spinner").removeClass("active");

                $(".password-message").html("<p class='error'>Something went wrong. Please try again.</p>");
                $("#easy-password-result").fadeIn(300);
            },
        });
    });

    // Handle copy to clipboard functionality
    $("#copy-password").on("click", function () {
        const password = $("#password-text").text();
        if (password) {
            // Create temporary textarea for copy
            const tempTextarea = $("<textarea>");
            $("body").append(tempTextarea);
            tempTextarea.val(password).select();
            document.execCommand("copy");
            tempTextarea.remove();

            // Show copied message
            $(this).addClass("copied");
            setTimeout(() => {
                $(this).removeClass("copied");
            }, 1500);
        }
    });
});
