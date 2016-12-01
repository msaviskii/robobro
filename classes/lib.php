<?
function generate_id ($length = 8) {
                $characters = '0123456789';
                $result = '';
                for ($i = 0; $i <= $length; $i++) {
                        $result .= $characters[mt_rand (0, strlen ($characters) - 1)];
                }
                return $result;
        }
		
?>