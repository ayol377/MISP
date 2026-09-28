<?php

/**
 * Low-res land mask for the DotMap render kind (dashboard v2).
 *
 * A 150 x 60 equirectangular grid (lon -180..180, lat 84..-60,
 * 2.4 degree cells, Antarctica dropped), rasterised from the vendored
 * Natural Earth 110m geometry (webroot/js/dashboard/charts/vendor/
 * world-110m.geojson). Each land cell carries the ISO alpha-2 code of the
 * country it falls in ("--" = land with no code). Countries too small to
 * own a cell centre get the cell holding their centroid (taken from a
 * larger neighbour if need be), so a hot small
 * country is never invisible.
 *
 * Encoding: one string per row, space-separated runs "<ISO><start>.<len>"
 * with start/len in base 36. Generated, do not hand-edit.
 */
class DotMapLand
{
    const COLS = 150;
    const ROWS = 60;

    const ROW_RUNS = [
        'CA15.8 GL1k.a',
        'CAz.3 CA15.5 GL1b.l NO2b.3 RU2m.2 RU35.3',
        'CAs.1 CAy.1 CA13.4 GL19.m NO29.2 NO2c.1 RU38.1',
        'CAq.5 CAw.2 CAz.3 CA13.2 GL1e.h RU2r.3 RU35.a RU3o.3',
        'CAn.3 CAx.1 CAz.2 CA12.1 CA14.3 GL1g.d RU2p.2 RU31.g RU3i.2',
        'US9.3 CAq.6 CAz.2 CA12.8 GL1g.c GL1t.1 NO2d.2 RU2p.2 RU2v.2 RU2y.z',
        'RU0.1 US6.a CAg.c CAu.1 CAy.1 CA10.2 CA13.2 CA18.3 GL1h.a SE2a.2 FI2c.3 RU2f.4 RU2l.1 RU2q.5 RU2w.1a',
        'RU1.3 US5.b CAg.n CA18.5 GL1h.7 IS1t.1 IS1v.2 NO28.1 SE29.4 FI2d.3 RU2g.1 RU2k.1m',
        'US3.1 US8.8 CAg.l CA13.1 CA19.1 CA1b.1 GL1i.4 NO27.1 SE28.3 FI2c.4 RU2g.1p',
        'US6.a CAg.k CA17.2 GL1j.2 NO25.3 SE28.2 FI2c.3 RU2f.1h RU3z.4',
        'USa.1 USh.2 CAj.h CA16.7 NO25.2 SE28.2 EE2d.1 RU2e.1c RU3x.2',
        'US8.1 CAk.i CA17.6 GB21.1 DK26.2 SE28.2 LT2c.1 LV2d.2 RU2f.19 RU3w.3',
        'CAj.1 CAl.k CA16.9 IE1z.1 GB22.1 DE27.2 PL29.4 BY2d.3 RU2g.d KZ2t.6 RU2z.p RU3w.2',
        'CAm.j CA16.9 GB21.3 BE24.1 NL25.1 DE26.3 PL29.4 UA2d.1 BY2e.2 UA2g.1 RU2h.7 KZ2o.1 RU2p.3 KZ2s.8 RU30.8 MN38.1 RU39.8 CN3h.3 RU3k.7 RU3w.1',
        'CAm.1 CAo.b USz.1 CA10.b CA1f.2 FR22.3 LU25.1 DE26.2 CZ28.3 SK2b.1 UA2c.8 RU2k.3 KZ2n.g CN33.1 MN34.b CN3f.6 RU3l.4 RU3q.1',
        'USn.h CA14.6 US1a.1 CA1b.1 CA1g.1 FR22.4 CH26.1 IT27.1 AT28.1 SI29.1 HU2a.2 RO2c.2 MD2e.1 UA2f.3 RU2j.4 KZ2n.1 KZ2p.d CN32.3 MN35.c CN3h.6 RU3n.2 RU3q.1',
        'USn.i CA15.2 US17.4 CA1b.1 FR22.4 IT26.2 HR29.1 BA2a.1 RS2b.1 RO2c.3 RU2j.4 KZ2o.2 UZ2q.2 KZ2s.8 CN30.6 MN36.7 CN3d.9 RU3m.2 JP3q.1',
        'USn.h CA14.1 US15.5 PT1z.1 ES20.4 IT28.1 AL2b.1 MK2c.1 BG2d.1 TR2e.1 TR2h.1 GE2k.2 RU2m.1 TM2p.1 UZ2q.1 TM2r.1 UZ2s.3 KZ2v.1 UZ2w.1 KG2x.3 CN30.a MN3a.1 CN3b.9 KP3k.1 JP3p.1',
        'USn.l PT1z.1 ES20.3 GR2b.2 TR2e.7 AM2l.1 AZ2m.2 TM2p.4 UZ2t.3 KG2w.2 CN2y.j KP3j.1 JP3p.1',
        'USo.j PT1z.1 ES20.2 GR2c.1 TR2e.8 IR2m.2 IR2p.3 TM2s.2 AF2u.1 TJ2v.1 AF2w.2 CN2y.k KR3k.1 JP3o.2',
        'USp.i MA20.2 DZ22.4 TN26.1 CY2g.1 LB2h.1 SY2i.2 IQ2k.2 IR2m.6 AF2s.5 PK2x.2 IN2z.1 CN30.h JP3m.3',
        'MXq.1 USr.e MA1z.3 DZ22.5 TN27.1 LY28.1 LY2b.2 PS2h.1 JO2i.1 IQ2j.4 IR2n.5 AF2s.4 PK2w.2 IN2y.2 CN30.i JP3l.1',
        'MXs.3 USv.7 US13.2 MA1z.2 DZ21.6 LY27.6 EG2d.4 IL2h.1 JO2i.1 SA2j.2 IQ2l.1 KW2m.1 IR2o.4 AF2s.3 PK2v.3 IN2y.3 CN31.h',
        'MXr.1 MXt.5 US15.1 MA1y.1 DZ1z.8 LY27.6 EG2d.4 SA2i.5 IR2p.4 PK2t.3 IN2w.5 NP31.3 BT34.1 IN35.2 MM37.1 CN38.9',
        'MXs.1 MXu.4 US15.1 BS16.1 MA1x.1 MR1y.3 DZ21.6 LY27.6 EG2d.4 SA2i.6 QA2o.1 IR2s.1 PK2v.2 IN2x.7 BD34.1 IN35.1 MM36.2 CN38.9',
        'MXv.3 CU14.2 MA1w.1 EH1x.1 MR1y.2 ML20.2 DZ22.5 NE27.2 TD29.1 LY2a.3 EG2d.5 SA2j.6 AE2p.1 OM2q.2 IN2w.8 BD34.2 MM36.2 CN38.2 VN3a.1 CN3b.4 TW3h.1',
        'MXv.4 MX11.2 CU17.1 MR1w.5 ML21.3 DZ24.2 NE26.4 TD2a.2 LY2c.1 SD2d.5 SA2k.6 OM2q.1 IN2x.6 MM36.3 LA39.2',
        'MXw.6 BZ12.1 JM16.1 HT18.1 DO19.1 PR1b.1 MR1w.5 ML21.4 NE25.4 TD29.4 SD2d.6 SA2k.3 YE2n.2 OM2p.2 IN2x.5 MM36.2 TH38.2 LA3a.1 PH3h.1',
        'MX10.1 GT11.1 HN12.2 SN1w.2 MR1y.3 ML21.4 NE25.4 TD29.4 SD2d.5 ER2i.2 YE2l.4 IN2y.2 TH38.3 LA3b.1 PH3h.1',
        'SV11.1 NI13.1 GM1w.1 SN1x.1 ML1y.3 BF21.2 NE23.2 NG25.1 NE26.1 NG27.2 TD29.3 SD2c.6 ET2i.2 ER2k.1 YE2l.1 IN2y.2 TH38.2 KH3a.2 VN3c.1 PH3h.2',
        'CR13.1 CO18.1 VE19.2 TT1d.1 GN1x.3 ML20.1 BF21.1 GH22.1 BJ23.2 NG25.4 TD29.3 CF2c.1 SD2d.3 SS2g.1 ET2h.3 DJ2k.1 SO2m.2 IN2z.1 KH3a.1 VN3b.1',
        'PA14.3 CO17.2 VE19.5 SL1y.1 GN1z.1 CI20.2 GH22.1 TG23.1 NG24.4 CM28.1 TD29.2 CF2b.2 SS2d.4 ET2h.5 SO2m.2 IN2z.1 LK30.1 TH38.1 PH3i.2',
        'CO17.4 VE1b.2 GY1d.2 LR1z.1 CI20.2 GH22.1 TG23.1 NG25.2 CM27.2 CF29.5 SS2e.4 ET2i.4 SO2m.1 LK30.1 MY39.1 MY3f.1',
        'CO17.4 VE1b.1 BR1c.2 GY1e.1 SR1f.1 FR1g.1 BR1h.1 CM27.2 CF29.2 CD2b.5 UG2g.1 KE2h.2 ET2j.1 SO2k.3 ID37.1 MY39.1 BN3e.1 ID3f.1',
        'CO16.4 BR1a.1 VE1b.1 BR1c.6 GQ27.1 GA28.1 CG29.1 CD2a.6 UG2g.1 KE2h.3 SO2k.1 ID38.2 ID3c.1 MY3d.1 ID3e.2 ID3k.1',
        'EC15.3 CO18.2 BR1a.a GA27.2 CG29.1 CD2a.5 RW2f.1 TZ2g.1 KE2h.3 ID39.1 ID3d.3 ID3h.1 ID3l.2',
        'PE15.5 BR1a.d GA27.1 CG28.2 CD2a.5 BI2f.1 TZ2g.3 KE2j.1 ID3a.1 ID3h.1 ID3m.4 PG3q.1 PG3u.1',
        'PE15.4 BR19.f AO28.2 CD2a.5 TZ2f.4 ID3b.1 ID3p.1 PG3q.2 PG3t.1 PG3v.1',
        'PE16.2 BR18.g AO28.4 CD2c.3 ZM2f.1 TZ2g.3 ID3e.1 ID3g.1 TL3j.1 ID3o.2 PG3q.1 PG3s.1',
        'PE17.3 BR1a.1 BO1b.1 BR1c.c AO29.3 CD2c.3 ZM2f.2 TZ2h.3 SB3y.1',
        'PE17.3 BO1a.3 BR1d.a AO28.4 ZM2c.5 MZ2h.3 MG2n.1 AU3l.3 AU3q.1',
        'PE18.2 BO1a.4 BR1e.9 AO28.4 ZM2c.3 ZW2f.1 MZ2g.1 MW2h.1 MZ2i.2 MG2m.2 AU3j.5 AU3q.2 VU40.1',
        'PE19.1 BO1a.5 BR1f.8 NA28.4 BW2c.2 ZW2e.3 MZ2h.1 MG2l.3 AU3i.a FJ45.1',
        'BO1a.3 PY1d.2 BR1f.7 NA28.4 BW2c.2 ZW2e.3 MG2l.2 AU3g.d NC3z.1',
        'CL1a.1 AR1b.2 PY1d.3 BR1g.6 NA29.2 BW2b.4 ZA2f.1 MZ2g.2 MG2l.2 AU3e.g',
        'AR1a.5 PY1f.1 BR1g.3 NA29.2 BW2b.3 ZA2e.2 MZ2g.1 AU3e.h',
        'CL19.1 AR1a.6 BR1g.3 NA29.2 ZA2b.5 SZ2g.1 AU3f.g',
        'CL19.1 AR1a.5 BR1f.3 ZA2a.4 LS2e.1 ZA2f.1 AU3f.g',
        'CL19.1 AR1a.5 UY1f.2 ZA2b.4 AU3f.4 AU3n.8',
        'CL19.1 AR1a.5 AU3f.1 AU3p.5',
        'CL18.1 AR19.6 AU3p.4',
        'CL18.1 AR19.4 NZ44.1',
        'CL18.1 AR19.3 AU3s.1 NZ42.2',
        'CL18.1 AR19.3 NZ41.1',
        'CL18.1 AR19.2',
        'AR18.3 TF2v.1',
        'CL18.1 AR19.1 FK1e.1',
        'CL18.2 AR1a.1',
        '',
        '',
    ];

    /**
     * @return array list of [row, col, len, iso] runs
     */
    public static function runs()
    {
        $runs = [];
        foreach (self::ROW_RUNS as $row => $line) {
            if ($line === '') {
                continue;
            }
            foreach (explode(' ', $line) as $run) {
                list($start, $len) = explode('.', substr($run, 2));
                $runs[] = [$row, (int)base_convert($start, 36, 10), (int)base_convert($len, 36, 10), substr($run, 0, 2)];
            }
        }
        return $runs;
    }
}
