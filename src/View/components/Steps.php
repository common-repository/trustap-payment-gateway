<?php

defined('ABSPATH') || exit;

?>
<tr valign="top">
    <th></th>
    <td>
        <div class="trustap-step-container" >
            <?php foreach (range(1, $total) as $step) {
                $step_content = $step;
                if ($step < $current) {
                    $step_class = 'success';
                    $step_content = "<img src='$checkmark_icon'/>";
                } elseif ($step === $current) {
                    $step_class = 'active';
                } else {
                    $step_class = 'disabled';
                }
                $step_container = "
                    <div class='trustap-step $step_class'>$step_content</div>
                ";

                if (!($step === $total)) {
                    $step_container .= "
                        <div class='trustap-step-space'>...</div>
                    ";
                }
                echo wp_kses_post($step_container);
            }
            ?>
        </div>
    </td>
</tr>
