<?php
include_once 'common.inc';
PageHeader("Requirements Test");
?>

<body>
<div class="container">
<div class="row justify-content-center mt-4">
<div class="col-lg-10">

<?php
displayRequirementsCheck();

// Additional standalone htaccess test
$result = checkHtaccessSupport();
?>

<div class="card mt-4">
<div class="card-header bg-info text-white">
<h5 class="mb-0">Standalone htaccess Detection Test</h5>
</div>
<div class="card-body">
<p><strong>Status:</strong> <?php echo $result['status'] ? 'Working' : 'Not Working'; ?></p>
<p><strong>Message:</strong> <?php echo htmlspecialchars($result['message']); ?></p>
<p><strong>Fix:</strong> <?php echo htmlspecialchars($result['fix']); ?></p>
</div>
</div>

</div>
</div>
</div>

<?php
PageFooter('TippingPoint', 'test@example.com', '1.3.0');
?>
</body>
</html>