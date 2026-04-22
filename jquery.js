$(document).ready(function(){
	$("#addMasjid").click(function()
	{
		$(location).attr('href', 'http://www.gomedina.com/Masjids_dev/mobile/add.php');
	});	
	$("#Home").click(function()
	{
		//alert("Home clicked");
		$(location).attr('href', 'http://www.gomedina.com/Masjids_dev/mobile');
	});	
	$("#login").click(function()
	{
		//alert("Login clicked");
		$(location).attr('href', 'http://www.gomedina.com/Masjids_dev/mobile/login.php');
	});
	$("#logout").click(function()
	{
		//alert("Logout clicked");
		$(location).attr('href', 'http://www.gomedina.com/Masjids_dev/mobile/logout.php');
	});
});	



