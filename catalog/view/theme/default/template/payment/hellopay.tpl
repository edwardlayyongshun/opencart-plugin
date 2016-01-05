<?php if ($error) { ?>
<div class="alert alert-danger" role="alert"><?php echo $errorMessage; ?></div>
<?php } else { ?>
<form action="<?php echo $action; ?>" method="post">
    <div class="buttons">
        <div class="pull-right">
            <input type="submit" value="<?php echo $button_confirm; ?>" class="btn btn-primary"/>
        </div>
    </div>
</form>
<?php } ?>
