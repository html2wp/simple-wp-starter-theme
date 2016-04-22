<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?php echo $response['message']; ?></title>

	<style>

		@import url( "https://fonts.googleapis.com/css?family=Source+Sans+Pro:400" );

		html,
		body,
		.container {
			margin: 0;
			height: 100%;
			width: 100%;
		}

		body {
			background-color: #ffffff;
			font-family: 'Source Sans Pro', sans-serif;
			font-size: 16px;
			-webkit-font-smoothing: antialiased;
			font-weight: 400;
			color: #242C39;
		}

		.container {
			text-align: center;
			position: absolute;
			display: table;        
			text-align: center;
		}

		.vertical-center {
			display: table-cell;
			vertical-align: middle;         
		}

		h1 {
			font-weight: 400;
		}

		.success h1 {
			color: #1dd637;
		}

		.error h1 {
			color: #ff0000;
		}

		p {
			font-size: 18px;
		}

		a {
			color: #242C39;
			text-decoration: underline;
		}

	</style>
</head>
<body>
	<div class="container">
		<?php if ( $success ) : ?>
			<div class="vertical-center success">
				<h1><?php echo $response[0]; ?></h1>
				<p><a href="<?= get_site_url(); ?>">Go home</a></p>
			</div>
		<?php else: ?>
			<div class="vertical-center error">
				<h1>There was an error submitting your form.</h1>
				<p>Error: <?php echo $response[0]; ?></p>
				<p>Please try again. <a href="javascript: window.history.back();">Go back</a></p>
			</div>
		<?php endif; ?>
	</div>
</body>
</html>