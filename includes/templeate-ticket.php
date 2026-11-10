<div id="voucherCapture">
    <div style="max-width:520px;margin:14px auto;font-family:Montserrat,'Segoe UI',Arial,sans-serif;background:#f7f7f7;padding:10px;border-radius:14px;border:1px solid #e5e5e5;">

        <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation" style="
            background:#ffffff;
            border-radius:12px;
            overflow:hidden;
            color:#0D0D0D;
            border-collapse:collapse;
            border:1px solid #e5e5e5;
            border-top:3px solid #D99C2B;
            box-shadow:0 4px 16px rgba(13,13,13,0.06);
        ">

            <tr>
                <td style="padding:12px 14px 10px;border-bottom:1px solid #eeeeee;background:#ffffff;">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation" style="border-collapse:collapse;">
                        <tr>
                            <td align="left" valign="middle" style="vertical-align:middle;width:75%;">
                                <img src="{LogoUrl}" width="120" height="auto" alt="{SiteName}" style="display:block;border:0;max-width:120px;width:100%;height:auto;margin:0;">
                            </td>
                            <td align="right" valign="middle" width="44" style="vertical-align:middle;width:44px;white-space:nowrap;">
                                <table border="0" cellspacing="0" cellpadding="0" align="right" role="presentation" style="border-collapse:collapse;margin:0;">
                                    <tr>
                                        <td align="center" valign="middle" width="32" height="32" style="width:32px;height:32px;text-align:center;vertical-align:middle;mso-line-height-rule:exactly;line-height:32px;">
                                            <a href="javascript:void(0)" onclick="shareVoucher(this)" title="Compartir"
                                               style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;border-radius:50%;background:#ffffff;border:1px solid #e5e5e5;text-decoration:none;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#555555" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;display:inline-block;">
                                                    <circle cx="18" cy="5" r="3"></circle>
                                                    <circle cx="6" cy="12" r="3"></circle>
                                                    <circle cx="18" cy="19" r="3"></circle>
                                                    <line x1="8.6" y1="13.5" x2="15.4" y2="17.5"></line>
                                                    <line x1="15.4" y1="6.5" x2="8.6" y2="10.5"></line>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td style="padding:12px 16px 6px;" align="left">
                    <p style="font-size:12px;margin:0;color:#555555;">Hola,</p>
                    <strong style="color:#0D0D0D;font-size:15px;display:block;margin-top:3px;">
                        {Nombre Cliente}
                    </strong>
                </td>
            </tr>

            <tr>
                <td style="padding:0 16px 12px;">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation" style="font-size:11px;border-top:1px solid #eeeeee;padding-top:10px;border-collapse:collapse;">

                        <tr>
                            <td style="padding:4px 12px 4px 0;color:#555555;white-space:nowrap;vertical-align:top;width:1%;"><strong style="color:#0D0D0D;">ID:</strong></td>
                            <td style="padding:4px 0;color:#0D0D0D;font-weight:600;text-align:left;">#{ID}</td>
                        </tr>

                        <tr>
                            <td style="padding:4px 12px 4px 0;color:#555555;white-space:nowrap;vertical-align:top;width:1%;"><strong style="color:#0D0D0D;">Fecha Compra:</strong></td>
                            <td style="padding:4px 0;color:#0D0D0D;text-align:left;">{Fecha}</td>
                        </tr>

                        <tr>
                            <td style="padding:4px 12px 4px 0;color:#555555;white-space:nowrap;vertical-align:top;width:1%;"><strong style="color:#0D0D0D;">Cant. nros:</strong></td>
                            <td style="padding:4px 0;color:#0D0D0D;font-weight:700;font-size:12px;text-align:left;">{Cantidad}</td>
                        </tr>

                        <tr>
                            <td style="padding:4px 12px 4px 0;color:#555555;white-space:nowrap;vertical-align:top;width:1%;"><strong style="color:#0D0D0D;">Fecha evento:</strong></td>
                            <td style="padding:4px 0;color:#0D0D0D;text-align:left;">{FechaEvento}</td>
                        </tr>

                        <tr>
                            <td style="padding:4px 12px 4px 0;color:#555555;vertical-align:top;white-space:nowrap;width:1%;"><strong style="color:#0D0D0D;">Evento:</strong></td>
                            <td style="padding:4px 0;color:#555555;line-height:1.35;text-align:left;">{Evento}</td>
                        </tr>

                        <tr>
                            <td colspan="2" style="padding:8px 0 4px;color:#555555;">
                                <strong style="color:#0D0D0D;">Código de Seguridad:</strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding:8px 10px;background:#f7f7f7;border-radius:8px;color:#0D0D0D;font-family:Consolas,'Courier New',monospace;font-size:14px;text-align:center;border:1px solid #e5e5e5;font-weight:700;letter-spacing:1.5px;">
                                {Codigo}
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:0 16px 12px;">
                    <div style="border:1px solid #eeeeee;border-radius:10px;padding:12px 10px;background:#ffffff;">
                        <p style="margin:0 0 8px;font-size:10px;color:#555555;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;">
                            Tus nros
                        </p>
                        <div style="text-align:center;">
                            {NumerosHTML}
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <td align="center" style="padding:10px 14px 9px;background:#f7f7f7;border-top:1px solid #eeeeee;">

                    <p style="margin:0;color:#777777;font-size:9px;text-transform:uppercase;letter-spacing:0.8px;font-weight:600;line-height:1.2;">
                        Total pagado
                    </p>

                    <p style="margin:2px 0 7px;color:#8C5716;font-size:16px;font-weight:800;line-height:1.2;">
                        {Total}
                    </p>

                    <p style="margin:6px 0 0;font-size:8px;color:#999999;line-height:1.3;">
                        Hemos enviado una copia a tu correo electrónico.
                    </p>

                    <table border="0" cellspacing="0" cellpadding="0" role="presentation" align="center" style="margin:14px auto 0;border-collapse:collapse;">
                        <tr>
                            <td align="center" bgcolor="#14AB55" style="border-radius:16px;background:#14AB55;background-image:linear-gradient(#3FE68A,#14AB55 48%,#0A8440);">
                                <a href="https://chat.whatsapp.com/GMyvIbj5fNbHNsremVNgAB?s=sh&amp;p=i&amp;mlu=4&amp;ilr=4&amp;iam=2" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 18px;border-radius:16px;background:#14AB55;background-image:linear-gradient(#3FE68A,#14AB55 48%,#0A8440);color:#ffffff;font-size:13px;font-weight:800;text-decoration:none;line-height:1.2;font-family:Montserrat,'Segoe UI',Arial,sans-serif;">
                                    <img src="{WhatsappIconUrl}" width="18" height="18" alt="" style="display:inline-block;vertical-align:middle;border:0;margin:0 8px 2px 0;width:18px;height:18px;">Únete a nuestra comunidad
                                </a>
                            </td>
                        </tr>
                    </table>
                    <p style="margin:6px 0 0;font-size:10px;color:#666666;line-height:1.3;">
                        Únete a nuestras dinámicas diarias
                    </p>

                </td>
            </tr>

        </table>
    </div>
</div>
