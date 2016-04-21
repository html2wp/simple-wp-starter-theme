<?php  ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $response['message']; ?></title>
<style type="text/css">
    body {
        margin: 0;
    }
    #form-submit-wrapper {
        text-align: center;
        position: absolute;
        height: 100%;
        width: 100%;
        display: table;        
        text-align: center;
    }
    #form-submit-wrapper h1 {

    }
    #form-submit-wrapper .success h1 {
        color: #34B73A;
    }   
    #form-submit-wrapper .error h1 {
        color: #F93F3F;
    }
    #form-submit-wrapper .error, #form-submit-wrapper .success {
        display: table-cell;
        vertical-align: middle;         
    }      
    #form-submit-wrapper p {
        /*color: ;*/
        font-size: 18px;
    } 
    #form-submit-wrapper p a {
        text-decoration: underline;
    }            
</style>
</head>
<body>
    <div id="form-submit-wrapper">
    <?php if ($response['success'] == 1) { ?>
    <div class="success">
        <h1><?php echo $response['message']; ?></h1>
        <p><a href="<?= get_site_url(); ?>">Go home</a></p>
    </div>
    <?php } else { ?>
    <div class="error">
        <h1>There was an error submitting your form.</h1>
        <p>Error: <?php echo $response['message']; ?></p>
        <p>Please try again. <a href="javascript: window.history.back();">Go back</a></p>
    </div>
    <?php } ?>
    </div>
</body>
</html>