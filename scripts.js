$(document).ready(function(){
	$("#submit").click(function(){
		var name = $("#username").val();
		//var email = $("#email").val();
		var password = $("#password").val();
		//var contact = $("#contact").val();
		// Returns successful data submission message when the entered information is stored in database.
		var dataString = 'name1='+ name + '&password1='+ password ;
		//alert(dataString);
		if(name==''||password=='')
		{
			alert("Please Fill All Fields");
		}
		else
		{
			// AJAX Code To Submit Form.
			$.ajax({
				type: "POST",
				url: "ajaxsubmit.php",
				data: dataString,
				cache: false,
				success: function(result){
					//alert(result);
					if(result == 1){
						//alert(result);
						window.location = "index.php"
					}
					else{
						alert("Wrong Credentials");
					}
				}
			});
		}
		return false;
	});
});