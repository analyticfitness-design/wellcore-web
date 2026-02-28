/**
 * WellCore Fitness — Video Library v1.0
 * Vincula ejercicios del plan de entrenamiento con videos de YouTube.
 * 256 videos indexados | Búsqueda por keywords | Modal player
 * Uso: WC_Videos.find(nombre, genero) / WC_Videos.init()
 */

const WC_Videos = (function () {

  // ─── BASE DE DATOS (256 videos) ─────────────────────────────────────────────
  // g: "h" = hombre, "m" = mujer
  const DB = [
    // HOMBRE
    { title: "Aperturas inclinado con mancuernas",          id: "OU9b_aRjz0Y", g: "h" },
    { title: "Aperturas inclinado en maquina",              id: "HDPKH1BBhhE", g: "h" },
    { title: "Copa de triceps",                             id: "CjqtisT2B2Y", g: "h" },
    { title: "Copa de triceps variante",                    id: "QvburrzD-7Q", g: "h" },
    { title: "Cruce en polea",                              id: "ZGUsf_jioSk", g: "h" },
    { title: "Cruce inferior poleas",                       id: "GyZKQvsGaeM", g: "h" },
    { title: "Cruce invertido con polea",                   id: "T4__GSGtnoU", g: "h" },
    { title: "Cruce polea baja",                            id: "BvuFsc_Co2E", g: "h" },
    { title: "Elevaciones con mancuernas dropset",          id: "uiYMBTuXhf0", g: "h" },
    { title: "Elevaciones laterales con mancuerna",         id: "Oj3T6YBfRCE", g: "h" },
    { title: "Elevaciones laterales en maquina",            id: "a_ltR5M_itA", g: "h" },
    { title: "Elevaciones laterales en polea",              id: "ADOXpWcHsZ4", g: "h" },
    { title: "Elevacion frontal con barra sentado",         id: "zlbmI4BthLk", g: "h" },
    { title: "Elevacion frontal con disco",                 id: "10e8v6Lna4k", g: "h" },
    { title: "Elevacion frontal con mancuernas",            id: "vV5bQObGHRE", g: "h" },
    { title: "Elevacion frontal con polea",                 id: "6yoOlcgHvhA", g: "h" },
    { title: "Elevacion lateral a una mano",                id: "6vRVVU9AzgE", g: "h" },
    { title: "Elevacion lateral con banco inclinado",       id: "dCimwp911N0", g: "h" },
    { title: "Extension de triceps a una mano",             id: "p3qIwP5ablo", g: "h" },
    { title: "Extension de triceps con barra",              id: "JQa9YeIzF44", g: "h" },
    { title: "Extension de triceps con barra variante",     id: "hDpQ6jcrT18", g: "h" },
    { title: "Extension de triceps con soga",               id: "q052hSZWh0M", g: "h" },
    { title: "Extension de triceps en banco inclinado",     id: "OAJXHoY2_2I", g: "h" },
    { title: "Extension de triceps por encima cabeza",      id: "bX4zOCT_Na8", g: "h" },
    { title: "Extension de triceps sentado en maquina",     id: "YeCM-Vl98gE", g: "h" },
    { title: "Extension de triceps sobre la cabeza",        id: "_j18KAzEKmI", g: "h" },
    { title: "Extension de triceps variacion poleas altas", id: "oTRVf2y4kEA", g: "h" },
    { title: "Facepull",                                    id: "iLnhqZ_oLsQ", g: "h" },
    { title: "Facepull variante jalon",                     id: "x6cgN0bTFRo", g: "h" },
    { title: "Fondos en banco",                             id: "SswE_mcoZLA", g: "h" },
    { title: "Fondos en banco variante",                    id: "AJWUCcTZvwY", g: "h" },
    { title: "Fondos en banco variante 2",                  id: "ueQ-QSMueXQ", g: "h" },
    { title: "Fondos en maquina",                           id: "rRw-yiVkE3M", g: "h" },
    { title: "Fondos en maquina variante",                  id: "7U5J8j0m850", g: "h" },
    { title: "Jalon al pecho",                              id: "MHhvz5IBFXk", g: "h" },
    { title: "Jalon al pecho estrecho",                     id: "sH7p91ExA0c", g: "h" },
    { title: "Levantamiento de hombros",                    id: "glX87IEgh6M", g: "h" },
    { title: "Patada de triceps con mancuerna",             id: "qwtikDRLKN4", g: "h" },
    { title: "Press con barra",                             id: "AANwuvNPPSU", g: "h" },
    { title: "Press arbol con mancuernas",                  id: "zAXy_By--o0", g: "h" },
    { title: "Press banca plano con barra",                 id: "dSKZeei9KJ4", g: "h" },
    { title: "Press banca plano barra variante",            id: "FPJS6TTZWL8", g: "h" },
    { title: "Press de agarre cerrado en banco",            id: "Q0n0Q4hxRLA", g: "h" },
    { title: "Press en maquina",                            id: "ojlEhV37FkU", g: "h" },
    { title: "Press en smith",                              id: "jYrcwuseZaM", g: "h" },
    { title: "Press inclinado con barra hombre",            id: "X2WPUgFQbWk", g: "h" },
    { title: "Press inclinado con mancuernas",              id: "1iL_WlAYBAs", g: "h" },
    { title: "Press inclinado con maquina",                 id: "jGDYhsGlMCs", g: "h" },
    { title: "Press inclinado en smith",                    id: "LXYKjob0KMo", g: "h" },
    { title: "Press militar con mancuernas",                id: "RhPWH-D6SRc", g: "h" },
    { title: "Press plano con mancuernas hombre",           id: "cma9jYjBRIw", g: "h" },
    { title: "Press plano en maquina",                      id: "D7wyM1rwbNE", g: "h" },
    { title: "Press plano en smith",                        id: "uPP6AJp1sT0", g: "h" },
    { title: "Pullover hombre",                             id: "SANzF6jptFs", g: "h" },
    { title: "Remo con polea baja a una mano",              id: "Gp-pRgcqWCE", g: "h" },
    { title: "Remo en maquina hombre",                      id: "tfbBm9tWAWo", g: "h" },
    { title: "Remo unilateral hombre",                      id: "1UL6Sb17RRI", g: "h" },
    { title: "Rompecraneos con mancuerna",                  id: "Sxlw9N3qACs", g: "h" },
    { title: "Rompecraneos con barra",                      id: "vVf4jueIBHo", g: "h" },
    { title: "Smith close grip triceps",                    id: "Rf9Bx5coELg", g: "h" },
    { title: "Vuelos posteriores con mancuerna",            id: "ow-y0-3HSKs", g: "h" },
    // MUJER - C2
    { title: "Abducciones con banda",                       id: "ft_qDZC7QnI", g: "m" },
    { title: "Abducciones sentada con banda",               id: "b0jv90Qih6E", g: "m" },
    { title: "Abduccion con disco o mancuerna",             id: "8K4dTRTfJ5M", g: "m" },
    { title: "Abs bicicleta",                               id: "BFHncTNWe00", g: "m" },
    { title: "Abs bicicleta variante",                      id: "Nnc7l8Ui-jg", g: "m" },
    { title: "Aperturas en inclinado mujer",                id: "JG0Gyco1664", g: "m" },
    { title: "Biceps martillo",                             id: "mXhhhqF8MbI", g: "m" },
    { title: "Burpees",                                     id: "GdRLK7socSM", g: "m" },
    { title: "Calentamiento tren superior",                 id: "uB3WDNUJOAI", g: "m" },
    { title: "Calentamiento tren superior variante",        id: "j14NpUbYQJM", g: "m" },
    { title: "Crunch declinado",                            id: "h2IUzciB0mA", g: "m" },
    { title: "Crunch elevacion de piernas",                 id: "UH3DpHaX4YQ", g: "m" },
    { title: "Crunch maquina",                              id: "JgFq4f3oSX0", g: "m" },
    { title: "Crunch polea",                                id: "5_jZ7CKMpTo", g: "m" },
    { title: "Curl biceps con barra z",                     id: "JAaR8C38wPI", g: "m" },
    { title: "Curl biceps con mancuerna",                   id: "THYMFbiVSDk", g: "m" },
    { title: "Curl biceps mancuernas",                      id: "ln3x9uhfzvw", g: "m" },
    { title: "Curl de biceps maquina",                      id: "Z4XapQSbds8", g: "m" },
    { title: "Curl femoral acostado unilateral",            id: "8C996YE6KmU", g: "m" },
    { title: "Curl femoral sentado",                        id: "_-NyXOSAVJs", g: "m" },
    { title: "Elevaciones de piernas isometrica",           id: "9E022U0Jj5A", g: "m" },
    { title: "Elevaciones laterales mujer",                 id: "jRoCjRhPY-U", g: "m" },
    { title: "Elevaciones laterales con banda",             id: "N-LHUKb4BHc", g: "m" },
    { title: "Estocada inversa",                            id: "xiunjTP15WQ", g: "m" },
    { title: "Extension con soga",                          id: "cvjAa27BV6A", g: "m" },
    { title: "Facepull mujer",                              id: "5HdOwU4-maI", g: "m" },
    { title: "Facepull mujer variante",                     id: "o-KnBSJrq8A", g: "m" },
    { title: "Fondos en banco mujer",                       id: "XO4PplkSW8s", g: "m" },
    { title: "Fondos en maquina mujer",                     id: "b5il9OgDZtw", g: "m" },
    { title: "Hip burnout",                                 id: "Osx9OU6IUBs", g: "m" },
    { title: "Hip con banda",                               id: "nA4TSLe0qJo", g: "m" },
    { title: "Hip thrush unilateral",                       id: "Py0uxrqPvZ8", g: "m" },
    { title: "Jumping jacks",                               id: "ObM_FCJPf5Y", g: "m" },
    { title: "Laterales maquina",                           id: "qc5-yIIYD1g", g: "m" },
    { title: "Levantamiento de piernas gluteo",             id: "MWrfvTp3U9k", g: "m" },
    { title: "Mountain climbers",                           id: "Jo7s6fhBguo", g: "m" },
    { title: "Movilidad calentamiento tren superior",       id: "XZs6eIldoJw", g: "m" },
    { title: "Patada polea mujer",                          id: "Pyan0Dn0c9s", g: "m" },
    { title: "Peso muerto con mancuernas mujer",            id: "k4irtnqxrB4", g: "m" },
    { title: "Plancha con toque de hombro",                 id: "vjOpMtBwB9w", g: "m" },
    { title: "Plancha isometrica",                          id: "DD16wKeVcDY", g: "m" },
    { title: "Press inclinado con barra mujer",             id: "SzAvnvjvXaw", g: "m" },
    { title: "Press inclinado mancuerna mujer",             id: "0nNOmd_HRKU", g: "m" },
    { title: "Press maquina mujer",                         id: "b6dalkSMLd8", g: "m" },
    { title: "Press militar mujer",                         id: "o-Yovd8uwo4", g: "m" },
    { title: "Press paralelo maquina",                      id: "QZm3Cb0NJTQ", g: "m" },
    { title: "Press plano mancuernas mujer",                id: "nKnfVOhC4-s", g: "m" },
    { title: "Pullover mujer",                              id: "kruSkUuz39A", g: "m" },
    { title: "Push up",                                     id: "mz7P-uktpF0", g: "m" },
    { title: "Push up con silla",                           id: "qkzrstl0nHA", g: "m" },
    { title: "Ranita",                                      id: "ijChgmaoYF4", g: "m" },
    { title: "Remo con banda",                              id: "jJvsfMB6WoU", g: "m" },
    { title: "Remo con barra mujer",                        id: "9Xfga6otn0o", g: "m" },
    { title: "Remo en maquina mujer",                       id: "Or7vXG2pPpQ", g: "m" },
    { title: "Remo inclinado",                              id: "Fd8jUCriVos", g: "m" },
    { title: "Remo polea baja",                             id: "hAkPnsdKxuo", g: "m" },
    { title: "Remo unilateral mujer",                       id: "WZ0gau8-UZ0", g: "m" },
    { title: "Rotaciones rusas con disco",                  id: "zG2SEAjlqIM", g: "m" },
    { title: "Step up con mancuernas",                      id: "Hu2NLQ00Qvg", g: "m" },
    { title: "Sumo con mancuerna",                          id: "wPdIKT2S96g", g: "m" },
    { title: "Tijera con step",                             id: "Ghxlvkee7qM", g: "m" },
    { title: "Toque de pie abdominales",                    id: "nFJTwXfckJk", g: "m" },
    { title: "Toque de pie gluteo",                         id: "gQJ3Y0xZw8I", g: "m" },
    { title: "Vuelos posteriores mujer",                    id: "M3HvviVcwj4", g: "m" },
    { title: "Vuelos posteriores variante",                 id: "dGrl2bofZ4c", g: "m" },
    { title: "Walkout pushup",                              id: "tR5WsbB_DZE", g: "m" },
    // MUJER - C3
    { title: "Abduccion cuadrupedia",                       id: "DlzFWScdRqs", g: "m" },
    { title: "Abduccion en maquina",                        id: "GLMW11Y28rE", g: "m" },
    { title: "Activacion gluteo",                           id: "H4Wc8HVD1ks", g: "m" },
    { title: "Activacion tren inferior",                    id: "W-7iyNWWMTE", g: "m" },
    { title: "Arremetida lateral aductores",                id: "Ragc9FOTfLI", g: "m" },
    { title: "Bulgara",                                     id: "Zj6sM8zPfX4", g: "m" },
    { title: "Bulgara variante",                            id: "nmPcJ_nviPo", g: "m" },
    { title: "Bulgara sostenida",                           id: "XoYuVb15jJo", g: "m" },
    { title: "Calentamiento tren inferior",                 id: "lbpZuIFCW38", g: "m" },
    { title: "Calentamiento tren inferior variante",        id: "TXf2BAbdIFU", g: "m" },
    { title: "Crunch con disco piernas",                    id: "I-FVVSGnj0Q", g: "m" },
    { title: "Curl femoral acostado",                       id: "f9Hn9TXGRCE", g: "m" },
    { title: "Elevaciones de talones sentado",              id: "Dz_99ynSB5M", g: "m" },
    { title: "Elevacion talones hacka",                     id: "XlKjvzAwbtY", g: "m" },
    { title: "Extension cuadriceps",                        id: "S38RDZAgnCg", g: "m" },
    { title: "Fire hydrants",                               id: "CJ1DNDxcOUo", g: "m" },
    { title: "Goblet squat",                                id: "WFT3oovZJjg", g: "m" },
    { title: "Goblet squat burnout",                        id: "o0C_aF2h6m8", g: "m" },
    { title: "Hacka",                                       id: "MczmJGS1rIo", g: "m" },
    { title: "Hip thrush con banda",                        id: "qqPz13VueOY", g: "m" },
    { title: "Hip thrush pausado",                          id: "tWyJD9yblpw", g: "m" },
    { title: "Hip thrush unilateral banda mancuerna",       id: "SaacInK7xF4", g: "m" },
    { title: "Hip thrust",                                  id: "qi4BB4VqKbc", g: "m" },
    { title: "Hip thrust en maquina",                       id: "OmwIs3-zT9o", g: "m" },
    { title: "Hip thrust burnout",                          id: "Tw3OC9BewQU", g: "m" },
    { title: "Hip y abduccion con banda",                   id: "viyyISyPYmM", g: "m" },
    { title: "Hiperextensiones gluteo",                     id: "aKWdeLUdUnE", g: "m" },
    { title: "Jalon al pecho mujer",                        id: "z7YGvf0Zp1I", g: "m" },
    { title: "Jalon al pecho estrecho mujer",               id: "x4DO2Hj-MOU", g: "m" },
    { title: "Levantamiento talones smith",                  id: "FS6A4BynAqw", g: "m" },
    { title: "Monster walks",                               id: "bZ9AiVaGo-o", g: "m" },
    { title: "Monster walks variante",                      id: "7mDJlSXV-hE", g: "m" },
    { title: "Patada diagonal",                             id: "sd8Iky0zWBk", g: "m" },
    { title: "Patada maquina",                              id: "sxVpCX7loCk", g: "m" },
    { title: "Patada polea gluteo",                         id: "tGo6AHwogsk", g: "m" },
    { title: "Peso muerto con barra mujer",                 id: "bAhaR4NcWKU", g: "m" },
    { title: "Peso muerto mancuernas c3",                   id: "Yk_LGqljDMM", g: "m" },
    { title: "Peso muerto en smith",                        id: "lxTECdfbIZY", g: "m" },
    { title: "Peso muerto unilateral",                      id: "A0p6eAV3mB8", g: "m" },
    { title: "Peso muerto unilateral variante",             id: "QbyLZZefg2U", g: "m" },
    { title: "Pistol squat",                                id: "-rDcTy_8pWI", g: "m" },
    { title: "Plancha isometrica variante",                 id: "x3ygOytuOgU", g: "m" },
    { title: "Plancha isometrica avanzada",                 id: "X4bBTxcyHgw", g: "m" },
    { title: "Prensa",                                      id: "1xsByKwh7E0", g: "m" },
    { title: "Sentadilla en smith",                         id: "-q4UcO4uyuQ", g: "m" },
    { title: "Sentadilla goblet",                           id: "DuZbwIoVZN8", g: "m" },
    { title: "Sentadilla libre",                            id: "fl8YQHLV_Zc", g: "m" },
    { title: "Sentadilla sumo con mancuerna",               id: "_5HN0qa2Us0", g: "m" },
    { title: "Squat isometrica",                            id: "SMzEZNP_se0", g: "m" },
    { title: "Step up con mancuerna c3",                    id: "b4ilReYs5qo", g: "m" },
    { title: "Sumo squat con barra",                        id: "Fq5tJMYrP0M", g: "m" },
    // MUJER - C4
    { title: "90 90 transitions",                           id: "5J2uGXTpkMA", g: "m" },
    { title: "Abduccion con superbanda",                    id: "Wcr1E3SOXRk", g: "m" },
    { title: "Abduccion en smith",                          id: "fVdlH4sE7tk", g: "m" },
    { title: "Alt DB Snatches",                             id: "12aL7uvdKjI", g: "m" },
    { title: "Arm row",                                     id: "mo45K0omiGs", g: "m" },
    { title: "Arms push press",                             id: "Xzx3YSOmF5E", g: "m" },
    { title: "Blast off push ups",                          id: "517VC3YPA3M", g: "m" },
    { title: "Bootstrapper",                                id: "JwudgNN4PYg", g: "m" },
    { title: "Bulgara en smith",                            id: "77g32pGHqyw", g: "m" },
    { title: "Calentamiento general",                       id: "2lAcdTsR-wg", g: "m" },
    { title: "Cat to cow",                                  id: "2M9HfEq4HmA", g: "m" },
    { title: "Copa de triceps mujer",                       id: "m6tTHwWVvoY", g: "m" },
    { title: "Crunch con disco",                            id: "TELYN2yaOE8", g: "m" },
    { title: "Curl 21 con mancuernas",                      id: "-THhf7k7RyU", g: "m" },
    { title: "Curl barra en polea",                         id: "Kx5y8FRiYTU", g: "m" },
    { title: "Curl martillo con mancuernas",                id: "0NhYByTxPkg", g: "m" },
    { title: "DB bent over rows",                           id: "66wh4Egrfzs", g: "m" },
    { title: "DB deadlift to high pull",                    id: "1T8Dlae8Ve8", g: "m" },
    { title: "DB devils press",                             id: "aeEzZpTE-aA", g: "m" },
    { title: "DB lunge to step up",                         id: "oYJYcMNpUxU", g: "m" },
    { title: "DB mixed forward lunge",                      id: "sNRJQQxSysA", g: "m" },
    { title: "DB push press",                               id: "Rg9pR_HB29E", g: "m" },
    { title: "DB squat cleans",                             id: "VzE7RRy8BrY", g: "m" },
    { title: "Desplante cruzado",                           id: "xLogzM862Wg", g: "m" },
    { title: "Dual doble snatch",                           id: "LILN6mDbNxc", g: "m" },
    { title: "Dual rack squat",                             id: "PHuYlexCz4I", g: "m" },
    { title: "Estocada inversa con mancuerna",              id: "PaLuKUlFgUg", g: "m" },
    { title: "Estocada inversa con step",                   id: "zgAzqFptsUU", g: "m" },
    { title: "Estocadas con superbanda",                    id: "1zoKVlaj9mI", g: "m" },
    { title: "Extension triceps acostada",                  id: "wJDCAInHZ7o", g: "m" },
    { title: "Extension triceps una mano mujer",            id: "lKjFeBhPv2M", g: "m" },
    { title: "Extension invertida en smith",                id: "XSkO1DLHNM8", g: "m" },
    { title: "Fondos de silla",                             id: "hzUIEnYt-r4", g: "m" },
    { title: "Good mornings",                               id: "R5jRX_ruYzU", g: "m" },
    { title: "Half burpee",                                 id: "2F_AL5Ix58c", g: "m" },
    { title: "Hammer en polea",                             id: "GQu4yKy_jZ8", g: "m" },
    { title: "Hollow body",                                 id: "RxaYBxrO4dU", g: "m" },
    { title: "Jalon al pecho c4",                           id: "YyYnvjO5cMY", g: "m" },
    { title: "Low plank",                                   id: "P9T5Am6DhIg", g: "m" },
    { title: "Patada de gluteo superbanda",                 id: "RpOhoDesL9Y", g: "m" },
    { title: "Peso muerto rumano con mancuernas",           id: "LsyEOgj8sW4", g: "m" },
    { title: "Peso muerto unilateral con superbanda",       id: "93rX3HSx2lQ", g: "m" },
    { title: "Peso muerto unilateral en smith",             id: "eUja1DWtJyY", g: "m" },
    { title: "Peso muerto unilateral enfoque gluteo",       id: "qm7BgQg80pE", g: "m" },
    { title: "Prensa parcial",                              id: "JQSNqRmQNwY", g: "m" },
    { title: "Prensa unilateral",                           id: "rqfKmFMxN28", g: "m" },
    { title: "Press mancuernas mujer",                      id: "OUlqgCl1ZH0", g: "m" },
    { title: "Pullover situp",                              id: "vGPWrhomtgs", g: "m" },
    { title: "Push to plank triceps",                       id: "FyJRauM-bug", g: "m" },
    { title: "Push up variaciones",                         id: "-CwNRveAJZ4", g: "m" },
    { title: "Push ups",                                    id: "1bLePLi639Q", g: "m" },
    { title: "Remo con mancuernas",                         id: "DQ_hv0o9I_o", g: "m" },
    { title: "Remo con superbanda",                         id: "mtYUW8b9rqc", g: "m" },
    { title: "Remo unilateral c4",                          id: "1z_inkwdGWg", g: "m" },
    { title: "Reverse lunges",                              id: "MiNQlHbrrmU", g: "m" },
    { title: "Semi sumo en smith",                          id: "8Krms4nsNbE", g: "m" },
    { title: "Sentadilla con mancuernas",                   id: "EIKBPxYhYs4", g: "m" },
    { title: "Sentadilla sumo isometria",                   id: "I6SX7nDTM1E", g: "m" },
    { title: "Step up con smith",                           id: "fitWN122jZM", g: "m" },
    { title: "V ups",                                       id: "XSKttZFu0zU", g: "m" },
  ];

  // ─── BÚSQUEDA ──────────────────────────────────────────────────────────────

  const STOP = new Set(["con","en","de","del","al","a","la","el","los","las","una","uno","y","o","por","para","un","su","variante","variante2","c2","c3","c4"]);

  function normalize(s) {
    return (s || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^a-z0-9\s]/g," ").replace(/\s+/g," ").trim();
  }

  function tokens(s) {
    return normalize(s).split(" ").filter(w => w.length > 1 && !STOP.has(w));
  }

  function scoreEntry(entry, qt) {
    const tt = tokens(entry.title);
    let s = 0;
    for (const q of qt) {
      for (const t of tt) {
        if (t === q)               s += 3;
        else if (t.startsWith(q)) s += 2;
        else if (t.includes(q))   s += 1;
        else if (q.includes(t) && t.length > 3) s += 1;
      }
    }
    return s;
  }

  function find(exerciseName, gender, maxResults) {
    const qt = tokens(exerciseName);
    if (!qt.length) return [];
    let results = DB
      .map(e => ({ ...e, _s: scoreEntry(e, qt) }))
      .filter(e => e._s > 0);
    if (gender) {
      const gm = results.filter(e => e.g === gender);
      if (gm.length) results = gm;
    }
    results.sort((a, b) => b._s - a._s);
    return results.slice(0, maxResults || 3).map(e => ({
      title: e.title, id: e.id, g: e.g,
      url:      "https://www.youtube.com/watch?v=" + e.id,
      embedUrl: "https://www.youtube.com/embed/" + e.id + "?autoplay=1&rel=0"
    }));
  }

  // ─── ESTILOS (inyectados una vez) ─────────────────────────────────────────

  function injectStyles() {
    if (document.getElementById("wcv-css")) return;
    const s = document.createElement("style");
    s.id = "wcv-css";
    s.textContent = [
      ".wcv-btn{display:inline-flex;align-items:center;gap:4px;background:rgba(227,30,36,.12);border:4px solid rgba(227,30,36,.35);color:#E31E24;font-size:10px;font-family:'JetBrains Mono',monospace;letter-spacing:1px;text-transform:uppercase;padding:3px 8px;border-radius:0;cursor:pointer;transition:background .1s linear,border-color .1s linear;white-space:nowrap;vertical-align:middle;margin-left:6px}",
      ".wcv-btn:hover{background:rgba(227,30,36,.22);border-color:#E31E24}",
      "#wcv-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:99999;align-items:center;justify-content:center}",
      "#wcv-overlay.open{display:flex}",
      "#wcv-box{background:#111113;border:4px solid #252528;border-radius:0;width:min(720px,92vw);overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.7)}",
      "#wcv-hdr{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #252528}",
      "#wcv-ttl{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#E31E24}",
      "#wcv-cls{background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:22px;line-height:1;padding:0 4px;transition:color .1s linear}",
      "#wcv-cls:hover{color:#fff}",
      "#wcv-wrap{position:relative;width:100%;padding-top:56.25%;background:#000}",
      "#wcv-iframe{position:absolute;inset:0;width:100%;height:100%;border:none}",
      "#wcv-ftr{padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}",
      "#wcv-yt{font-family:'JetBrains Mono',monospace;font-size:9px;color:rgba(255,255,255,.3);letter-spacing:1px;text-decoration:none;transition:color .1s linear}",
      "#wcv-yt:hover{color:#fff}",
      "#wcv-alts{display:flex;gap:8px;flex-wrap:wrap}",
      ".wcv-alt{font-family:'JetBrains Mono',monospace;font-size:9px;padding:3px 8px;border:4px solid #333;border-radius:0;color:rgba(255,255,255,.5);background:none;cursor:pointer;transition:border-color .1s linear,color .1s linear}",
      ".wcv-alt:hover,.wcv-alt.on{border-color:#E31E24;color:#E31E24}"
    ].join("");
    document.head.appendChild(s);
  }

  // ─── MODAL (construido con createElement, sin innerHTML) ─────────────────

  function buildModal() {
    if (document.getElementById("wcv-overlay")) return;

    const ov = document.createElement("div");
    ov.id = "wcv-overlay";

    const box = document.createElement("div");
    box.id = "wcv-box";

    // Header
    const hdr = document.createElement("div");
    hdr.id = "wcv-hdr";
    const ttl = document.createElement("span");
    ttl.id = "wcv-ttl";
    ttl.textContent = "VIDEO TUTORIAL";
    const cls = document.createElement("button");
    cls.id = "wcv-cls";
    cls.title = "Cerrar";
    cls.textContent = "\u00D7";
    cls.addEventListener("click", close);
    hdr.appendChild(ttl);
    hdr.appendChild(cls);

    // Video wrapper
    const wrap = document.createElement("div");
    wrap.id = "wcv-wrap";
    const ifr = document.createElement("iframe");
    ifr.id = "wcv-iframe";
    ifr.allow = "autoplay; encrypted-media";
    ifr.allowFullscreen = true;
    wrap.appendChild(ifr);

    // Footer
    const ftr = document.createElement("div");
    ftr.id = "wcv-ftr";
    const ytLink = document.createElement("a");
    ytLink.id = "wcv-yt";
    ytLink.target = "_blank";
    ytLink.rel = "noopener noreferrer";
    ytLink.textContent = "VER EN YOUTUBE";
    const alts = document.createElement("div");
    alts.id = "wcv-alts";
    ftr.appendChild(ytLink);
    ftr.appendChild(alts);

    box.appendChild(hdr);
    box.appendChild(wrap);
    box.appendChild(ftr);
    ov.appendChild(box);
    document.body.appendChild(ov);

    ov.addEventListener("click", e => { if (e.target === ov) close(); });
    document.addEventListener("keydown", e => { if (e.key === "Escape") close(); });
  }

  // ─── MODAL CONTROL ────────────────────────────────────────────────────────

  let _vids = [], _idx = 0;

  function open(videos, index) {
    _vids = videos;
    _idx  = index || 0;
    _load(_idx);

    const alts = document.getElementById("wcv-alts");
    while (alts.firstChild) alts.removeChild(alts.firstChild);

    if (videos.length > 1) {
      videos.forEach(function(v, i) {
        const b = document.createElement("button");
        b.className = "wcv-alt" + (i === _idx ? " on" : "");
        b.textContent = "V" + (i + 1);
        b.title = v.title;
        b.addEventListener("click", function() {
          _idx = i;
          _load(i);
          alts.querySelectorAll(".wcv-alt").forEach(function(x, j) {
            x.classList.toggle("on", j === i);
          });
        });
        alts.appendChild(b);
      });
    }

    document.getElementById("wcv-overlay").classList.add("open");
  }

  function _load(i) {
    const v = _vids[i];
    if (!v) return;
    document.getElementById("wcv-ttl").textContent = v.title.toUpperCase();
    document.getElementById("wcv-iframe").src = v.embedUrl;
    const yt = document.getElementById("wcv-yt");
    yt.href = v.url;
  }

  function close() {
    document.getElementById("wcv-overlay").classList.remove("open");
    document.getElementById("wcv-iframe").src = "";
  }

  // ─── BOTÓN ────────────────────────────────────────────────────────────────

  function createButton(exerciseName, gender) {
    const vids = find(exerciseName, gender, 5);
    if (!vids.length) return null;

    const btn = document.createElement("button");
    btn.className = "wcv-btn";
    btn.title = "Ver video: " + vids[0].title;

    // Play triangle (Unicode)
    const icon = document.createElement("span");
    icon.textContent = "\u25B6";
    icon.style.fontSize = "9px";
    const label = document.createElement("span");
    label.textContent = "VIDEO";

    btn.appendChild(icon);
    btn.appendChild(label);

    btn.addEventListener("click", function(e) {
      e.stopPropagation();
      open(vids, 0);
    });
    return btn;
  }

  // ─── AUTO-INIT ────────────────────────────────────────────────────────────

  function init() {
    injectStyles();
    buildModal();

    document.querySelectorAll("[data-exercise]").forEach(function(el) {
      var name   = el.getAttribute("data-exercise");
      var gender = el.getAttribute("data-gender") || null;
      var btn    = createButton(name, gender);
      if (!btn) return;
      var tag = el.tagName;
      if (tag === "TD" || tag === "TH" || tag === "LI") {
        el.appendChild(btn);
      } else {
        el.insertAdjacentElement("afterend", btn);
      }
    });

    document.querySelectorAll(".exercise-name:not([data-exercise])").forEach(function(el) {
      var name = el.textContent.trim();
      var btn  = createButton(name, null);
      if (btn) el.insertAdjacentElement("afterend", btn);
    });
  }

  // ─── API PÚBLICA ──────────────────────────────────────────────────────────
  return { find: find, init: init, createButton: createButton, open: open, close: close, db: DB, count: DB.length };

}());

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function() { WC_Videos.init(); });
} else {
  WC_Videos.init();
}
