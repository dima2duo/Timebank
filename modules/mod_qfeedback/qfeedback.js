(function($){$.fn.contactable=function(from,fromm){var defaults={url:'/index.php?option=com_tranz&view=mail&layout=mail&no_html=1',name:'Ваше имя',email:'Ваш E-mail (гарантированная конфиденциальность)',message:'Сообщение',subject:'Обратная связь',page:location.href,submit:'Отправить письмо',recievedMsg:'Благодарим вас за письмо!',notRecievedMsg:'Извините, но при отправке письма произошла ошибка, попробуйте позже',disclaimer:'Для обратного ответа по телефону укажите его',hideOnSubmit:true,fromus:from,frommail:fromm};var options=$.extend(defaults,options);return this.each(function(){var this_id_prefix='#'+this.id+' ';$(this).html('<div id="contactable_inner"></div><form id="contactForm" method="" action=""><div id="loading"></div><div id="callback"></div><div class="holder"><p><label for="name">'+options.name+'<span class="red"> * </span></label><br /><input id="name" value="'+options.fromus+'" class="contact" name="name"/></p><p><label for="email">'+options.email+' <span class="red"> * </span></label><br /><input id="email" class="contact" value="'+options.frommail+'" name="email" /></p><p><label for="message">'+options.message+' <span class="red"> * </span></label><br /><textarea id="message" name="message" class="message" rows="10" cols="30" ></textarea></p><p><input class="submit" type="submit" value="'+options.submit+'"/></p><p class="disclaimer">'+options.disclaimer+'</p></div></form>');$(this_id_prefix+'div#contactable_inner').toggle(function(){$(this_id_prefix+'#overlay').css({display:'block'});$(this).animate({"marginLeft":"-=5px"},"fast");$(this_id_prefix+'#contactForm').animate({"marginLeft":"-=0px"},"fast");$(this).animate({"marginLeft":"+=387px"},"slow");$(this_id_prefix+'#contactForm').animate({"marginLeft":"+=390px"},"slow")},function(){$(this_id_prefix+'#contactForm').animate({"marginLeft":"-=390px"},"slow");$(this).animate({"marginLeft":"-=387px"},"slow").animate({"marginLeft":"+=5px"},"fast");$(this_id_prefix+'#overlay').css({display:'none'})});$(this_id_prefix+"#contactForm").validate({errorPlacement:function(error,element){return true},rules:{name:{required:true,minlength:2},email:{required:true,email:true},message:{required:true}},messages:{name:"",email:"",message:""},submitHandler:function(){$(this_id_prefix+'.holder').hide();$(this_id_prefix+'#loading').show();$.ajax({type:'POST',url:options.url,cache:false,data:{subject:options.subject,page:options.page,name:$(this_id_prefix+'#name').val(),email:$(this_id_prefix+'#email').val(),message:$(this_id_prefix+'#message').val()},success:function(data){$(this_id_prefix+'#loading').css({display:'none'});if($.trim(data)=='success'){$(this_id_prefix+'#callback').show().append(options.recievedMsg);if(options.hideOnSubmit==true){$(this_id_prefix+'#contactForm').animate({dummy:1},2000).animate({"marginLeft":"-=450px"},"slow");$(this_id_prefix+'div#contactable_inner').animate({dummy:1},2000).animate({"marginLeft":"-=447px"},"slow").animate({"marginLeft":"+=5px"},"fast");$(this_id_prefix+'#overlay').css({display:'none'})}}else{$(this_id_prefix+'#callback').show().append(options.notRecievedMsg);setTimeout(function(){$(this_id_prefix+'.holder').show();$(this_id_prefix+'#callback').hide().html('')},2000)}},error:function(){$(this_id_prefix+'#loading').css({display:'none'});$(this_id_prefix+'#callback').show().append(options.notRecievedMsg)}})}})})}})(jQuery);