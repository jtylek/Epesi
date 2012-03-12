<center>
<br/>
<table id="Utils_Attachment__download_info" cellpadding="0" style="width:300px; border-spacing: 3px;">
	<tr>
		<td class="epesi_label" style="width:30%;">
			{$labels.filename}
		</td>
		<td class="epesi_data static_field" style="width:70%;">
			{$filename}
		</td>
	</tr>
	<tr>
		<td class="epesi_label" style="width:30%;">
			{$labels.file_size}
		</td>
		<td class="epesi_data static_field" style="width:70%;">
			{$file_size}
		</td>
	</tr>
</table>
<br/>
<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
	<tr>
		<!-- VIEW -->
		<td valign="top">
			{$__link.view.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/view.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.view.text}</span>
				</div>
			{$__link.view.close}
		</td>
		<!-- DOWNLOAD -->
		<td valign="top">
			{$__link.download.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/download.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.download.text}</span>
				</div>
			{$__link.download.close}
		</td>
		<!-- LINK -->
		<td valign="top">
			{$__link.link.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/link.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.link.text}</span>
				</div>
			{$__link.link.close}
		</td>
	</tr>
</table>

<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
	<tr>
	{assign var=x value=0}
	{foreach item=p key=k from=$custom_getters}
	{assign var=x value=$x+1}
		
		<td valign="top">
			{$p.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/{$p.icon}" alt="" align="middle" border="0" width="32" height="32">
					<span>{$p.text}</span>
				</div>
			{$p.close}
		</td>
	{if ($x%4)==0}
	</tr>
	<tr>
	{/if}

{/foreach}
	</tr>
</table>
</center>
