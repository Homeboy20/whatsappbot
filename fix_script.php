<?php
// Fix duplicate function declaration
$file = 'includes/whatsapp-handler.php';
$content = file_get_contents($file);

// Add function_exists check to the first declaration
$fixed_content = str_replace(
    "// Generate mobile money push notification with retry option\nfunction kwetupizza_generate_mobile_money_push",
    "// Generate mobile money push notification with retry option\nif (!function_exists('kwetupizza_generate_mobile_money_push')) {\nfunction kwetupizza_generate_mobile_money_push",
    $content
);

// Add closing bracket after the first function definition
$fixed_content = str_replace(
    "}
}

/**
 * Enhanced mobile money push function with better error handling
 */
function kwetupizza_generate_mobile_money_push",
    "}
}
}

/**
 * Enhanced mobile money push function with better error handling
 */
if (!function_exists('kwetupizza_generate_mobile_money_push')) {\nfunction kwetupizza_generate_mobile_money_push",
    $fixed_content
);

// Add closing bracket before "Keep existing functions"
$fixed_content = str_replace(
    "}

// Keep existing functions",
    "}
}

// Keep existing functions",
    $fixed_content
);

// Save the fixed content
file_put_contents($file . '.fixed', $fixed_content);
echo "Fixed file saved as {$file}.fixed\n"; 